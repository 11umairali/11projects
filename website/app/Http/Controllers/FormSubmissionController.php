<?php

namespace App\Http\Controllers;

use App\Events\FormSubmitted;
use App\Models\Contact;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Handles public form submissions that trigger workflows with
 * subtype='form_submitted'. Each active form_submitted workflow has a public
 * URL at /form/{workflowId}. Users can either embed the rendered form, or
 * POST their own JSON from any external system (Webflow, Framer, etc.).
 */
class FormSubmissionController extends Controller
{
    /**
     * GET /form/{workflow}
     * Renders a minimal HTML form so the user can test submission without
     * building their own frontend. The field list comes from the trigger
     * node's config.fields array (defaults to just email).
     */
    public function show(int $workflowId): Response
    {
        $workflow = $this->resolveActiveFormWorkflow($workflowId);
        if (!$workflow) abort(404);

        $triggerNode = $workflow->workflowNodes()
            ->where('type', 'trigger')
            ->where('subtype', 'form_submitted')
            ->first();

        $fields = $triggerNode?->config['fields'] ?? ['email'];
        $title = $triggerNode?->config['form_title'] ?? $workflow->name;

        return response($this->renderFormHtml($workflow->id, $title, $fields));
    }

    /**
     * POST /form/{workflow}
     * Accepts form submission. Expects at minimum an `email` field. Creates
     * or updates a Contact in the workspace, then fires FormSubmitted.
     */
    public function submit(int $workflowId, Request $request): JsonResponse|RedirectResponse
    {
        // Rate limit: 30 submissions per minute per IP per form to block abuse
        $key = 'form-submit:' . $workflowId . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json(['error' => 'Too many submissions. Please wait.'], 429);
        }
        RateLimiter::hit($key, 60);

        $workflow = $this->resolveActiveFormWorkflow($workflowId);
        if (!$workflow) {
            return response()->json(['error' => 'Form not found or inactive.'], 404);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
        ]);

        // Find or create contact in the workflow's workspace
        $contact = Contact::withoutGlobalScope('workspace')
            ->where('workspace_id', $workflow->workspace_id)
            ->where('email', $validated['email'])
            ->first();

        if (!$contact) {
            $contact = Contact::create([
                'workspace_id' => $workflow->workspace_id,
                'email' => $validated['email'],
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'company' => $validated['company'] ?? null,
                'source' => 'form',
                'status' => 'active',
            ]);
        } else {
            // Fill blanks from submission, never overwrite existing values
            $updates = array_filter([
                'first_name' => $contact->first_name ?: ($validated['first_name'] ?? null),
                'last_name' => $contact->last_name ?: ($validated['last_name'] ?? null),
                'phone' => $contact->phone ?: ($validated['phone'] ?? null),
                'company' => $contact->company ?: ($validated['company'] ?? null),
            ]);
            if (!empty($updates)) {
                $contact->update($updates);
            }
        }

        // Fire FormSubmitted — the listener dispatches ExecuteWorkflowJob so
        // the actual action runs async, keeping this response fast.
        try {
            event(new FormSubmitted($workflow, $contact, $validated));
        } catch (\Throwable $e) {
            Log::warning("FormSubmission: dispatch failed for workflow={$workflow->id}: {$e->getMessage()}");
        }

        // If the caller sent a browser form (no Accept: application/json),
        // redirect to a thank-you page; otherwise return JSON.
        if (!$request->expectsJson()) {
            return redirect()->to(url("/form/{$workflowId}?submitted=1"));
        }

        return response()->json(['ok' => true, 'contact_id' => $contact->id]);
    }

    private function resolveActiveFormWorkflow(int $workflowId): ?Workflow
    {
        return Workflow::where('id', $workflowId)
            ->where('status', 'active')
            ->whereHas('workflowNodes', fn ($q) => $q->where('type', 'trigger')->where('subtype', 'form_submitted'))
            ->first();
    }

    private function renderFormHtml(int $workflowId, string $title, array $fields): string
    {
        $titleSafe = e($title);
        $appName = e(config('app.name', 'MailTrixy'));
        $submitted = request()->query('submitted') === '1';
        $action = url("/form/{$workflowId}");
        $csrf = csrf_token();

        // Standard field list — form trigger config.fields can include any of
        // these; email is always required.
        $available = [
            'email' => ['label' => 'Email', 'type' => 'email', 'required' => true],
            'first_name' => ['label' => 'First Name', 'type' => 'text', 'required' => false],
            'last_name' => ['label' => 'Last Name', 'type' => 'text', 'required' => false],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'required' => false],
            'company' => ['label' => 'Company', 'type' => 'text', 'required' => false],
            'message' => ['label' => 'Message', 'type' => 'textarea', 'required' => false],
        ];

        // Always include email, plus whatever's configured
        $toRender = array_values(array_unique(array_merge(['email'], $fields)));

        $inputs = '';
        foreach ($toRender as $f) {
            if (!isset($available[$f])) continue;
            $def = $available[$f];
            $label = e($def['label']);
            $type = e($def['type']);
            $req = $def['required'] ? 'required' : '';
            $name = e($f);

            if ($type === 'textarea') {
                $inputs .= "<label><span>{$label}</span><textarea name=\"{$name}\" rows=\"4\" {$req}></textarea></label>";
            } else {
                $inputs .= "<label><span>{$label}</span><input type=\"{$type}\" name=\"{$name}\" {$req}></label>";
            }
        }

        $successBanner = $submitted
            ? '<div class="ok">Thanks — we got your submission.</div>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$titleSafe} — {$appName}</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f9fafb;color:#111827;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:16px}
.card{background:#fff;border-radius:16px;padding:40px;max-width:480px;width:100%;box-shadow:0 1px 3px rgba(0,0,0,.08)}
h1{font-size:22px;margin:0 0 8px}
p.sub{color:#6b7280;margin:0 0 24px;font-size:14px}
label{display:block;margin-bottom:14px}
label span{display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px}
input,textarea{width:100%;padding:10px 12px;font-size:14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;font-family:inherit}
input:focus,textarea:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.15)}
button{background:#4f46e5;color:#fff;padding:10px 24px;border:0;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer;width:100%;margin-top:8px}
button:hover{background:#4338ca}
.ok{background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
.footer{margin-top:24px;text-align:center;font-size:12px;color:#9ca3af}
</style></head>
<body><div class="card">
{$successBanner}
<h1>{$titleSafe}</h1>
<p class="sub">Fill out the form below.</p>
<form method="POST" action="{$action}">
<input type="hidden" name="_token" value="{$csrf}">
{$inputs}
<button type="submit">Submit</button>
</form>
<p class="footer">Powered by {$appName}</p>
</div></body></html>
HTML;
    }
}
