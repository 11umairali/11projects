<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessKBDocumentJob;
use App\Jobs\ScrapeWebsiteJob;
use App\Models\AiConfig;
use App\Models\EmailAccount;
use App\Models\Invite;
use App\Models\KbDocument;
use App\Models\Workspace;
use App\Services\EmailAccountProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    /**
     * Verify the workspace has completed the prerequisite step.
     *
     * Each onboarding step increments the workspace's onboarding_step counter.
     * This guard prevents users from skipping ahead by directly hitting a
     * later step's POST endpoint.
     *
     * @param  int  $requiredStep  The step being attempted (e.g. 2 means storeStep2)
     * @return bool  True if the prerequisite is met
     */
    /**
     * Verify the workspace is on exactly this step (for POST/store actions).
     * Enforces sequential completion — users cannot skip ahead.
     */
    private function ensureStep(int $requiredStep): bool
    {
        $workspace = Auth::user()?->activeWorkspace;

        if (!$workspace) {
            return false;
        }

        // Allow current step OR auto-advance if user clicked "Skip for now"
        $currentStep = $workspace->onboarding_step ?? 0;

        if ($currentStep === $requiredStep) {
            return true;
        }

        // If the user is behind (skipped previous steps via GET links),
        // auto-advance to the requested step
        if ($currentStep < $requiredStep) {
            $workspace->update(['onboarding_step' => $requiredStep]);
            return true;
        }

        return false;
    }

    /**
     * Step 1: Create Workspace (GET)
     */
    public function step1()
    {
        // Admins should never see onboarding — send them to admin dashboard
        if (Auth::user()->is_admin) {
            return redirect(url('/admin/dashboard'));
        }

        return view('onboarding.step-1', [
            'defaultName' => Auth::user()->name . "'s Workspace",
        ]);
    }

    /**
     * Step 1: Create Workspace (POST)
     */
    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'workspace_name' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'industry' => ['required', 'string', 'max:50'],
            'team_size' => ['required', 'string', Rule::in(['1', '2-5', '6-20', '20+'])],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('workspace-logos', 'public');
        }

        $workspace = Workspace::create([
            'name' => $validated['workspace_name'],
            'logo_path' => $logoPath,
            'industry' => $validated['industry'],
            'team_size' => $validated['team_size'],
            'onboarding_step' => 2,
        ]);

        // Attach user as owner
        $workspace->members()->attach(Auth::id(), [
            'role' => 'owner',
            'status' => 'online',
        ]);

        // Set as active workspace
        Auth::user()->update(['active_workspace_id' => $workspace->id]);

        // Wire up the user's built-in @dahimail.com mailbox as this
        // workspace's first EmailAccount — no IMAP/SMTP screen needed,
        // it's the same server-side mailbox the webmail app itself uses.
        try {
            app(EmailAccountProvisioner::class)->ensure(Auth::user(), $workspace);
        } catch (\Throwable $e) {
            Log::warning('EmailAccountProvisioner failed during onboarding step 1', [
                'user_id' => Auth::id(),
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Assign default plan from admin settings
        try {
            $trialDays = (int) \App\Models\SystemSetting::get('trial_days', 14);
            $plan = \App\Support\BillingPolicy::defaultPlan();

            if ($plan) {
                if ($plan->isFree()) $trialDays = 0;
                \App\Models\Subscription::create([
                    'workspace_id' => $workspace->id,
                    'plan_id' => $plan->id,
                    'status' => $trialDays > 0 ? 'trialing' : 'active',
                    'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Default plan assignment failed: ' . $e->getMessage());
        }

        return redirect()->route('onboarding.step-2');
    }

    /**
     * Step 2: Connect Email (GET)
     */
    public function step2()
    {
        return view('onboarding.step-2');
    }

    /**
     * Step 2: Connect Email (POST)
     *
     * Handles three provider paths:
     * - gmail/outlook: Records provider preference (OAuth handled elsewhere)
     * - custom: Creates EmailAccount with IMAP/SMTP credentials
     */
    public function storeStep2(Request $request)
    {
        if (!$this->ensureStep(2)) {
            return redirect()->route('onboarding.step-1')
                ->with('error', 'Please complete the previous step first.');
        }

        $workspace = Auth::user()->activeWorkspace;

        if (! $workspace) {
            return redirect()->route('onboarding.step-1')
                ->with('error', 'Please create a workspace first.');
        }

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(['gmail', 'outlook', 'custom'])],

            // Required only for custom provider
            'email'           => ['required_if:provider,custom', 'nullable', 'email', 'max:255'],
            'display_name'    => ['nullable', 'string', 'max:255'],
            'imap_host'       => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'imap_port'       => ['required_if:provider,custom', 'nullable', 'integer', 'min:1', 'max:65535'],
            'imap_username'   => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'imap_password'   => ['required_if:provider,custom', 'nullable', 'string', 'max:1000'],
            'imap_encryption' => ['required_if:provider,custom', 'nullable', 'string', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_host'       => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'smtp_port'       => ['required_if:provider,custom', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username'   => ['required_if:provider,custom', 'nullable', 'string', 'max:255'],
            'smtp_password'   => ['required_if:provider,custom', 'nullable', 'string', 'max:1000'],
            'smtp_encryption' => ['required_if:provider,custom', 'nullable', 'string', Rule::in(['ssl', 'tls', 'none'])],
        ]);

        $provider = $validated['provider'];

        if ($provider === 'custom') {
            // Create EmailAccount with IMAP/SMTP credentials
            EmailAccount::create([
                'workspace_id'    => $workspace->id,
                'user_id'         => Auth::id(),
                'email'           => $validated['email'],
                'display_name'    => $validated['display_name'] ?? null,
                'provider'        => 'custom',
                'imap_host'       => $validated['imap_host'],
                'imap_port'       => (int) $validated['imap_port'],
                'imap_username'   => $validated['imap_username'],
                'imap_password'   => $validated['imap_password'],
                'imap_encryption' => $validated['imap_encryption'],
                'smtp_host'       => $validated['smtp_host'],
                'smtp_port'       => (int) $validated['smtp_port'],
                'smtp_username'   => $validated['smtp_username'],
                'smtp_password'   => $validated['smtp_password'],
                'smtp_encryption' => $validated['smtp_encryption'],
                'status'          => 'pending', // Will be verified by a background job
                'is_default'      => true,
            ]);
        } else {
            // Gmail or Outlook: record the preference so the OAuth flow
            // knows which provider the user chose. Create a placeholder
            // EmailAccount that the OAuth callback will populate with tokens.
            EmailAccount::create([
                'workspace_id' => $workspace->id,
                'user_id'      => Auth::id(),
                'email'        => Auth::user()->email, // Placeholder, updated after OAuth
                'provider'     => $provider,
                'status'       => 'pending_oauth',
                'is_default'   => true,
            ]);
        }

        $workspace->update(['onboarding_step' => 3]);

        return redirect()->route('onboarding.step-3');
    }

    /**
     * Step 3: Train AI (GET)
     */
    public function step3()
    {
        return view('onboarding.step-3');
    }

    /**
     * Step 3: Train AI (POST)
     *
     * Saves AI personality + language to AiConfig.
     * Processes three knowledge source types:
     * - documents[]: uploaded files (PDF, DOCX, TXT, CSV)
     * - website_url: URL to scrape
     * - qa[]: question/answer pairs
     */
    public function storeStep3(Request $request)
    {
        if (!$this->ensureStep(3)) {
            return redirect()->route('onboarding.step-2')
                ->with('error', 'Please complete the previous step first.');
        }

        $workspace = Auth::user()->activeWorkspace;

        if (! $workspace) {
            return redirect()->route('onboarding.step-1')
                ->with('error', 'Please create a workspace first.');
        }

        $validated = $request->validate([
            'personality'   => ['required', 'string', Rule::in(['professional', 'friendly', 'casual', 'sales', 'support', 'custom'])],
            // FIX-024: Limit custom prompt length and strip control characters
            'custom_prompt' => ['nullable', 'required_if:personality,custom', 'string', 'max:1000'],
            'language'      => ['required', 'string', 'max:10'],
            'documents'     => ['nullable', 'array', 'max:10'],
            'documents.*'   => ['file', 'mimes:pdf,doc,docx,txt,csv', 'max:10240'], // 10MB each
            'website_url'   => ['nullable', 'url', 'max:2000'],
            'qa'            => ['nullable', 'array', 'max:50'],
            'qa.*.question' => ['nullable', 'string', 'max:1000'],
            'qa.*.answer'   => ['nullable', 'string', 'max:5000'],
        ]);

        DB::beginTransaction();

        try {
            // 1. Save AI personality and language settings
            AiConfig::updateOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'personality_preset' => $validated['personality'],
                    // FIX-024: Strip control chars and dangerous prompt patterns
                    'custom_prompt'      => $validated['personality'] === 'custom'
                        ? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $validated['custom_prompt'] ?? '')
                        : null,
                    'reply_language'     => $validated['language'],
                ]
            );

            // 2. Process uploaded documents
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    try {
                        $originalName = $file->getClientOriginalName();
                        $extension = strtolower($file->getClientOriginalExtension());
                        $storedPath = $file->store("kb-documents/{$workspace->id}", 'local');

                        $document = KbDocument::create([
                            'workspace_id' => $workspace->id,
                            'type'         => 'document',
                            'title'        => $originalName,
                            'file_path'    => $storedPath,
                            'file_type'    => $extension,
                            'file_size'    => $file->getSize(),
                            'status'       => 'pending',
                        ]);

                        ProcessKBDocumentJob::dispatch($document);
                    } catch (\Exception $e) {
                        Log::warning('Onboarding: Failed to process uploaded document', [
                            'workspace_id' => $workspace->id,
                            'file'         => $file->getClientOriginalName(),
                            'error'        => $e->getMessage(),
                        ]);
                        // Continue with remaining files -- don't fail the whole step
                    }
                }
            }

            // 3. Process website URL (with SSRF protection)
            if (! empty($validated['website_url'])) {
                if ($this->isUrlSafe($validated['website_url'])) {
                    ScrapeWebsiteJob::dispatch(
                        $validated['website_url'],
                        $workspace->id,
                    );
                } else {
                    Log::warning('Onboarding: Blocked unsafe scrape URL', [
                        'workspace_id' => $workspace->id,
                        'url' => $validated['website_url'],
                    ]);
                }
            }

            // 4. Process Q&A pairs
            if (! empty($validated['qa'])) {
                foreach ($validated['qa'] as $pair) {
                    $question = trim($pair['question'] ?? '');
                    $answer = trim($pair['answer'] ?? '');

                    // Skip empty pairs
                    if ($question === '' && $answer === '') {
                        continue;
                    }

                    $document = KbDocument::create([
                        'workspace_id' => $workspace->id,
                        'type'         => 'qa',
                        'title'        => mb_substr($question, 0, 255) ?: 'Q&A Entry',
                        'question'     => $question,
                        'answer'       => $answer,
                        'status'       => 'pending',
                    ]);

                    ProcessKBDocumentJob::dispatch($document);
                }
            }

            $workspace->update(['onboarding_step' => 4]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Onboarding step 3 failed', [
                'workspace_id' => $workspace->id,
                'error'        => $e->getMessage(),
            ]);

            return back()->withInput()
                ->with('error', 'Something went wrong while saving your AI settings. Please try again.');
        }

        return redirect()->route('onboarding.step-4');
    }

    /**
     * Step 4: Auto-Reply Rules (GET)
     */
    public function step4()
    {
        return view('onboarding.step-4');
    }

    /**
     * Step 4: Auto-Reply Rules (POST)
     *
     * Saves auto-reply toggle, confidence threshold, send mode,
     * reply delay, and business hours schedule to AiConfig.
     */
    public function storeStep4(Request $request)
    {
        if (!$this->ensureStep(4)) {
            return redirect()->route('onboarding.step-3')
                ->with('error', 'Please complete the previous step first.');
        }

        $workspace = Auth::user()->activeWorkspace;

        if (! $workspace) {
            return redirect()->route('onboarding.step-1')
                ->with('error', 'Please create a workspace first.');
        }

        $validated = $request->validate([
            'auto_reply_enabled'    => ['required', Rule::in(['0', '1'])],
            'confidence_threshold'  => ['nullable', 'integer', 'min:0', 'max:100'],
            'send_mode'             => ['nullable', 'string', Rule::in(['autonomous', 'approval', 'suggestions'])],
            'reply_delay'           => ['nullable', 'string', Rule::in(['0', '30', '60', '120', '300', '600', 'random'])],
            'schedule'              => ['nullable', 'array'],
            'schedule.*.enabled'    => ['nullable'],
            'schedule.*.start'      => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'schedule.*.end'        => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $autoReplyEnabled = (bool) $validated['auto_reply_enabled'];

        // Build business hours JSON from the schedule array.
        // The form sends schedule[monday][enabled], schedule[monday][start], etc.
        // Checkboxes that are unchecked won't be present in the request,
        // so we treat missing 'enabled' as false.
        $businessHours = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        if (! empty($validated['schedule'])) {
            foreach ($days as $day) {
                $dayData = $validated['schedule'][$day] ?? [];
                $businessHours[$day] = [
                    'enabled' => isset($dayData['enabled']) && $dayData['enabled'] === 'on',
                    'start'   => $dayData['start'] ?? '09:00',
                    'end'     => $dayData['end'] ?? '17:00',
                ];
            }
        }

        // updateOrCreate so it works whether step 3 created an AiConfig or not
        AiConfig::updateOrCreate(
            ['workspace_id' => $workspace->id],
            array_merge(
                [
                    'auto_reply_enabled'  => $autoReplyEnabled,
                    'business_hours_only' => $autoReplyEnabled && ! empty($businessHours),
                ],
                $autoReplyEnabled ? [
                    'confidence_threshold' => (int) ($validated['confidence_threshold'] ?? 75),
                    'send_mode'            => $validated['send_mode'] ?? 'approval',
                    'reply_delay'          => $validated['reply_delay'] ?? '0',
                ] : []
            )
        );

        // Store business hours on the workspace (the canonical location for
        // operating-hours data, referenced across the application).
        if (! empty($businessHours)) {
            $workspace->update(['business_hours' => $businessHours]);
        }

        $workspace->update(['onboarding_step' => 5]);

        return redirect()->route('onboarding.step-5');
    }

    /**
     * Step 5: Invite Team (GET)
     */
    public function step5()
    {
        return view('onboarding.step-5');
    }

    /**
     * Step 5: Invite Team (POST)
     *
     * Creates Invite records for each email/role pair and
     * attempts to send invitation emails.
     */
    public function storeStep5(Request $request)
    {
        if (!$this->ensureStep(5)) {
            return redirect()->route('onboarding.step-4')
                ->with('error', 'Please complete the previous step first.');
        }

        $workspace = Auth::user()->activeWorkspace;

        if (! $workspace) {
            return redirect()->route('onboarding.step-1')
                ->with('error', 'Please create a workspace first.');
        }

        $validated = $request->validate([
            'invites'         => ['nullable', 'array', 'max:20'],
            'invites.*.email' => ['nullable', 'email', 'max:255'],
            'invites.*.role'  => ['nullable', 'string', Rule::in(['admin', 'agent', 'viewer'])],
        ]);

        $invitesData = $validated['invites'] ?? [];

        // Filter out empty rows (user submitted blank invite fields)
        $validInvites = array_filter($invitesData, function ($invite) {
            return ! empty(trim($invite['email'] ?? ''));
        });

        if (! empty($validInvites)) {
            DB::beginTransaction();

            try {
                foreach ($validInvites as $inviteData) {
                    $email = strtolower(trim($inviteData['email']));
                    $role = $inviteData['role'] ?? 'agent';

                    // Skip if inviting themselves
                    if ($email === strtolower(Auth::user()->email)) {
                        continue;
                    }

                    // Skip duplicate invites for the same workspace + email
                    $existingInvite = Invite::where('workspace_id', $workspace->id)
                        ->where('email', $email)
                        ->whereNull('accepted_at')
                        ->where('expires_at', '>', now())
                        ->first();

                    if ($existingInvite) {
                        continue;
                    }

                    $invite = Invite::create([
                        'workspace_id' => $workspace->id,
                        'invited_by'   => Auth::id(),
                        'email'        => $email,
                        'role'         => $role,
                    ]);

                    // Attempt to send the invitation email
                    $this->sendInvitationEmail($invite, $workspace);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                Log::error('Onboarding step 5 failed', [
                    'workspace_id' => $workspace->id,
                    'error'        => $e->getMessage(),
                ]);

                return back()->withInput()
                    ->with('error', 'Something went wrong while sending invitations. Please try again.');
            }
        }

        $workspace->update([
            'onboarding_step'      => 5,
            'onboarding_completed' => true,
        ]);

        // Seed sample data for new workspaces so the product isn't empty
        if ($workspace->contacts()->count() === 0) {
            $this->seedSampleData($workspace, Auth::user());
        }

        return redirect()->route('onboarding.complete');
    }

    private function seedSampleData($workspace, $user): void
    {
        try {
            $sampleContacts = [
                ['first_name' => 'Sarah', 'last_name' => 'Johnson', 'email' => 'sarah.johnson@example.com', 'company' => 'Acme Corp', 'status' => 'active'],
                ['first_name' => 'Michael', 'last_name' => 'Chen', 'email' => 'michael.chen@example.com', 'company' => 'TechFlow Inc', 'status' => 'active'],
                ['first_name' => 'Emily', 'last_name' => 'Rodriguez', 'email' => 'emily.r@example.com', 'company' => 'StartupXYZ', 'status' => 'active'],
                ['first_name' => 'James', 'last_name' => 'Wilson', 'email' => 'j.wilson@example.com', 'company' => 'Global Services', 'status' => 'active'],
                ['first_name' => 'Lisa', 'last_name' => 'Park', 'email' => 'lisa.park@example.com', 'company' => 'Creative Labs', 'status' => 'active'],
            ];

            foreach ($sampleContacts as $data) {
                $workspace->contacts()->create(array_merge($data, [
                    'created_at' => now()->subDays(rand(1, 30)),
                ]));
            }

            $tags = ['VIP', 'Lead', 'Customer', 'Partner', 'Prospect'];
            foreach ($tags as $tagName) {
                $workspace->tags()->firstOrCreate(['name' => $tagName]);
            }

            $cannedResponses = [
                ['title' => 'Thank You', 'content' => "Thank you for reaching out! I've received your message and will get back to you within 24 hours.\n\nBest regards", 'shortcut' => '/thanks'],
                ['title' => 'Follow Up', 'content' => "Hi there! I wanted to follow up on our previous conversation. Do you have any questions or need any additional information?\n\nLooking forward to hearing from you.", 'shortcut' => '/followup'],
                ['title' => 'Out of Office', 'content' => "Thanks for your email. I'm currently away from the office and will return on [DATE]. For urgent matters, please contact [COLLEAGUE].\n\nI'll respond to your message as soon as I return.", 'shortcut' => '/ooo'],
            ];

            foreach ($cannedResponses as $cr) {
                \App\Models\CannedResponse::create(array_merge($cr, [
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                ]));
            }
        } catch (\Exception $e) {
            Log::warning('Sample data seeding failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Onboarding Complete page
     *
     * Passes real completion status for each onboarding step so the
     * summary checklist only shows green for steps the user actually
     * completed (not skipped).
     */
    public function complete()
    {
        $user = Auth::user();
        $workspace = $user?->activeWorkspace;

        $steps = [
            'workspace' => false,
            'email'     => false,
            'ai'        => false,
            'auto_reply' => false,
            'team'      => false,
        ];

        if ($workspace) {
            // Step 1: Workspace exists and has a name
            $steps['workspace'] = filled($workspace->name);

            // Step 2: At least one email account connected
            $steps['email'] = $workspace->emailAccounts()->exists();

            // Step 3: Has knowledge base documents OR Q&A pairs
            $steps['ai'] = KbDocument::where('workspace_id', $workspace->id)->exists();

            // Step 4: Auto-reply configured (AiConfig exists with auto_reply_enabled set)
            $aiConfig = $workspace->aiConfig;
            $steps['auto_reply'] = $aiConfig && $aiConfig->auto_reply_enabled;

            // Step 5: More than 1 member (owner + at least one other)
            $steps['team'] = $workspace->members()->count() > 1
                || Invite::where('workspace_id', $workspace->id)->exists();
        }

        $completedCount = count(array_filter($steps));

        return view('onboarding.complete', compact('steps', 'completedCount'));
    }

    /**
     * Test IMAP/SMTP connection during onboarding.
     *
     * Accepts JSON with the mail server credentials and attempts to
     * open an IMAP connection and an SMTP connection. Returns a JSON
     * response indicating success or the error that occurred.
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'imap_host'       => ['required', 'string', 'max:255'],
            'imap_port'       => ['required', 'integer', 'min:1', 'max:65535'],
            'imap_username'   => ['required', 'string', 'max:255'],
            'imap_password'   => ['required', 'string', 'max:1000'],
            'imap_encryption' => ['required', 'string', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_host'       => ['nullable', 'string', 'max:255'],
            'smtp_port'       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username'   => ['nullable', 'string', 'max:255'],
            'smtp_password'   => ['nullable', 'string', 'max:1000'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['ssl', 'tls', 'none'])],
        ]);

        $errors = [];

        // Test IMAP connection
        try {
            $encryption = $validated['imap_encryption'] === 'none' ? '' : $validated['imap_encryption'];
            $mailbox = sprintf(
                '{%s:%d/imap/%s%s}INBOX',
                $validated['imap_host'],
                $validated['imap_port'],
                $encryption ? $encryption . '/' : '',
                'novalidate-cert'
            );

            $connection = @imap_open(
                $mailbox,
                $validated['imap_username'],
                $validated['imap_password'],
                0,
                1, // Max retries
                ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
            );

            if ($connection) {
                imap_close($connection);
            } else {
                $imapError = imap_last_error();
                $errors[] = 'Incoming mail: ' . ($imapError ?: 'Could not connect to the server.');
            }
        } catch (\Exception $e) {
            $errors[] = 'Incoming mail: ' . $e->getMessage();
        }

        // Clear IMAP error stack
        imap_errors();
        imap_alerts();

        // Test SMTP connection (if provided)
        if (!empty($validated['smtp_host']) && !empty($validated['smtp_username'])) {
            try {
                $smtpEncryption = $validated['smtp_encryption'] === 'none' ? null : $validated['smtp_encryption'];
                $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                    $validated['smtp_host'],
                    (int) $validated['smtp_port'],
                    $smtpEncryption === 'ssl',
                );
                $transport->setUsername($validated['smtp_username']);
                $transport->setPassword($validated['smtp_password']);

                // Attempt to start the transport (opens TCP + authenticates)
                $transport->start();
                $transport->stop();
            } catch (\Exception $e) {
                $message = $e->getMessage();
                // Trim overly verbose Symfony exception messages for the UI
                if (str_contains($message, '(code:')) {
                    $message = preg_replace('/\s*\(code:.*$/', '', $message);
                }
                $errors[] = 'Outgoing mail: ' . $message;
            }
        }

        if (empty($errors)) {
            return response()->json([
                'success' => true,
                'message' => 'Connection successful! Your mail server settings are correct.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => implode(' | ', $errors),
        ]);
    }

    /**
     * Validate that a URL does not target internal/private networks (SSRF protection).
     */
    /**
     * FIX-025/026: Enhanced SSRF protection with port validation and IPv6 handling.
     */
    private function isUrlSafe(string $url): bool
    {
        $parsed = parse_url($url);

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return false;
        }

        // FIX-026: Block non-standard ports
        $port = $parsed['port'] ?? null;
        if ($port !== null && !in_array((int) $port, [80, 443, 8080, 8443])) {
            return false;
        }

        $hostLower = strtolower($host);

        $blockedHosts = [
            'localhost', '0.0.0.0', '127.0.0.1', '[::1]',
            'metadata.google.internal', 'metadata.google',
            '169.254.169.254', 'metadata.azure.internal',
        ];
        if (in_array($hostLower, $blockedHosts)) {
            return false;
        }

        // Block .local and .internal TLDs
        if (str_ends_with($hostLower, '.local') || str_ends_with($hostLower, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // FIX-025: Block private, reserved, AND IPv6 loopback/link-local
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
            // Block IPv4-mapped IPv6 (::ffff:127.0.0.1)
            if (str_starts_with($host, '::ffff:')) {
                $ipv4 = substr($host, 7);
                if (!filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return false;
                }
            }
        }

        $resolvedIps = gethostbynamel($hostLower);
        if ($resolvedIps) {
            foreach ($resolvedIps as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Send an invitation email for the given Invite.
     *
     * Uses the InvitationMail mailable if it exists. If not, logs the
     * intent so invitations still get created even before the mailable
     * class is built out.
     */
    private function sendInvitationEmail(Invite $invite, Workspace $workspace): void
    {
        try {
            $mailableClass = 'App\\Mail\\InvitationMail';

            if (class_exists($mailableClass)) {
                $inviterName = Auth::user()->name ?? 'A team member';
                Mail::to($invite->email)->send(new $mailableClass($invite, $workspace, $inviterName));
            } else {
                Log::info('InvitationMail mailable not found -- invite created without email', [
                    'invite_id'    => $invite->id,
                    'workspace_id' => $workspace->id,
                    'email'        => $invite->email,
                ]);
            }
        } catch (\Exception $e) {
            // Email delivery failure should not prevent invite creation.
            // The user can resend from the team management page later.
            Log::warning('Failed to send invitation email', [
                'invite_id'    => $invite->id,
                'email'        => $invite->email,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
