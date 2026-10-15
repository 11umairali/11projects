<?php

namespace App\Services\Campaign;

use App\Models\Contact;
use App\Models\DripEnrollment;
use App\Models\DripSequence;
use App\Models\DripStep;
use App\Services\Email\EmailSendService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DripService
{
    public function __construct(
        private readonly EmailSendService $emailSendService,
    ) {}

    /** FIX-069: Maximum number of retries for a failed drip step send. */
    protected const MAX_STEP_RETRIES = 2;

    /** FIX-069: Delay in minutes before retrying a failed drip step. */
    protected const RETRY_DELAY_MINUTES = 30;

    /**
     * Enroll a contact in a drip sequence.
     *
     * @throws \InvalidArgumentException If already enrolled or sequence is not active.
     */
    public function enrollContact(DripSequence $sequence, Contact $contact): DripEnrollment
    {
        if (! $sequence->isActive()) {
            throw new \InvalidArgumentException(
                "Drip sequence {$sequence->id} is not active (status: {$sequence->status})."
            );
        }

        // FIX-068: Skip enrollment for contacts that are not active
        if (in_array($contact->status, ['unsubscribed', 'bounced', 'spam'])) {
            Log::warning('Skipping drip enrollment for non-active contact', [
                'sequence_id' => $sequence->id,
                'contact_id' => $contact->id,
                'contact_status' => $contact->status,
            ]);

            // Return a non-persisted enrollment so callers don't need null-checks,
            // but it was never actually created in the database.
            return new DripEnrollment([
                'sequence_id' => $sequence->id,
                'contact_id' => $contact->id,
                'status' => 'exited',
                'completed_at' => now(),
            ]);
        }

        // Get the first step to calculate initial delay
        $firstStep = $sequence->steps()->orderBy('position')->first();

        $nextStepAt = $firstStep
            ? Carbon::now()->add($firstStep->getDelayInterval())
            : null;

        // Atomic: firstOrCreate prevents duplicate enrollment via unique constraint
        $enrollment = DripEnrollment::firstOrCreate(
            [
                'sequence_id' => $sequence->id,
                'contact_id' => $contact->id,
                'status' => 'active',
            ],
            [
                'current_step' => 0,
                'next_step_at' => $nextStepAt,
                'enrolled_at' => now(),
            ]
        );

        if (! $enrollment->wasRecentlyCreated) {
            Log::info('Contact already enrolled in drip sequence', [
                'sequence_id' => $sequence->id,
                'contact_id' => $contact->id,
                'enrollment_id' => $enrollment->id,
            ]);

            return $enrollment;
        }

        Log::info('Contact enrolled in drip sequence', [
            'enrollment_id' => $enrollment->id,
            'sequence_id' => $sequence->id,
            'contact_id' => $contact->id,
            'next_step_at' => $nextStepAt?->toDateTimeString(),
        ]);

        return $enrollment;
    }

    /**
     * Process the current step for a drip enrollment.
     *
     * @throws \RuntimeException On processing failure.
     */
    public function processStep(DripEnrollment $enrollment): void
    {
        $enrollment->load(['sequence.campaign.emailAccount', 'contact']);

        $sequence = $enrollment->sequence;
        $contact = $enrollment->contact;

        if (! $enrollment->isActive()) {
            Log::info('Skipping non-active enrollment', [
                'enrollment_id' => $enrollment->id,
                'status' => $enrollment->status,
            ]);

            return;
        }

        // Contact unsubscribed or bounced? Exit the drip.
        if (in_array($contact->status, ['unsubscribed', 'bounced', 'spam'])) {
            $enrollment->update([
                'status' => 'exited',
                'completed_at' => now(),
            ]);

            Log::info('Contact exited drip due to status', [
                'enrollment_id' => $enrollment->id,
                'contact_status' => $contact->status,
            ]);

            return;
        }

        // Get steps — use cached eager load if available, else query once
        $steps = $sequence->relationLoaded('steps')
            ? $sequence->steps->sortBy('position')->values()
            : $sequence->steps()->orderBy('position')->get();
        $currentStepIndex = $enrollment->current_step;

        if ($currentStepIndex >= $steps->count()) {
            // No more steps -- mark as completed
            $enrollment->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('Drip enrollment completed (no more steps)', [
                'enrollment_id' => $enrollment->id,
            ]);

            return;
        }

        $step = $steps[$currentStepIndex];

        try {
            $this->executeStepAction($step, $enrollment, $contact);

            // FIX-069: Reset retry count on success before advancing
            if ($enrollment->retry_count > 0) {
                $enrollment->update(['retry_count' => 0]);
            }

            // Advance to next step
            $this->advanceToNextStep($enrollment, $steps);

        } catch (\Throwable $e) {
            // FIX-069: Retry failed steps up to MAX_STEP_RETRIES times before skipping
            $retryCount = $enrollment->retry_count ?? 0;

            Log::error('Drip step processing failed', [
                'enrollment_id' => $enrollment->id,
                'step_position' => $step->position,
                'action_type' => $step->action_type,
                'retry_count' => $retryCount,
                'max_retries' => static::MAX_STEP_RETRIES,
                'error' => $e->getMessage(),
            ]);

            if ($retryCount < static::MAX_STEP_RETRIES) {
                // Schedule a retry: mark as failed, bump retry counter, set next attempt time
                $enrollment->update([
                    'status' => 'active',
                    'retry_count' => $retryCount + 1,
                    'next_step_at' => Carbon::now()->addMinutes(static::RETRY_DELAY_MINUTES),
                ]);

                Log::info('Drip step retry scheduled', [
                    'enrollment_id' => $enrollment->id,
                    'step_position' => $step->position,
                    'retry_attempt' => $retryCount + 1,
                    'next_retry_at' => Carbon::now()->addMinutes(static::RETRY_DELAY_MINUTES)->toDateTimeString(),
                ]);
            } else {
                // Max retries exhausted -- log and advance past the failed step
                Log::warning('Drip step max retries exhausted, advancing to next step', [
                    'enrollment_id' => $enrollment->id,
                    'step_position' => $step->position,
                    'action_type' => $step->action_type,
                ]);

                $enrollment->update(['retry_count' => 0]);
                $this->advanceToNextStep($enrollment, $steps);
            }
        }
    }

    /**
     * Execute the action defined by a drip step.
     */
    private function executeStepAction(DripStep $step, DripEnrollment $enrollment, Contact $contact): void
    {
        $data = $step->action_data ?? [];

        match ($step->action_type) {
            'send_email' => $this->executeSendEmail($data, $enrollment, $contact),
            'add_tag' => $this->executeAddTag($data, $contact),
            'remove_tag' => $this->executeRemoveTag($data, $contact),
            'update_field' => $this->executeUpdateField($data, $contact),
            'wait_condition' => null, // Wait conditions are handled by delay
            default => Log::warning("Unknown drip step action: {$step->action_type}"),
        };
    }

    /**
     * Send a drip email.
     */
    private function executeSendEmail(array $data, DripEnrollment $enrollment, Contact $contact): void
    {
        $subject = $data['subject'] ?? 'No Subject';
        $bodyHtml = $data['body_html'] ?? '';

        // Variable replacement
        $replacements = [
            '{first_name}' => $contact->first_name ?? '',
            '{last_name}' => $contact->last_name ?? '',
            '{full_name}' => $contact->full_name ?? '',
            '{company}' => $contact->company ?? '',
            '{email}' => $contact->email ?? '',
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $bodyHtml = str_replace(array_keys($replacements), array_values($replacements), $bodyHtml);

        // Get the email account from the campaign associated with this drip
        $emailAccount = $enrollment->sequence->campaign?->emailAccount;

        if (! $emailAccount) {
            throw new \RuntimeException('No email account configured for drip sequence.');
        }

        $this->emailSendService->send(
            account: $emailAccount,
            to: $contact->email,
            subject: $subject,
            htmlBody: $bodyHtml,
        );

        Log::info('Drip email sent', [
            'enrollment_id' => $enrollment->id,
            'contact_email' => $contact->email,
            'subject' => $subject,
        ]);
    }

    /**
     * Add a tag to the contact.
     */
    private function executeAddTag(array $data, Contact $contact): void
    {
        $tagId = $data['tag_id'] ?? null;

        if ($tagId) {
            $alreadyTagged = $contact->tags()->where('tags.id', $tagId)->exists();
            $contact->tags()->syncWithoutDetaching([$tagId]);
            Log::info('Drip: tag added to contact', [
                'contact_id' => $contact->id,
                'tag_id' => $tagId,
            ]);

            if (!$alreadyTagged) {
                $tag = \App\Models\Tag::find($tagId);
                if ($tag) {
                    try { event(new \App\Events\TagAdded($contact, $tag)); } catch (\Throwable $e) {}
                }
            }
        }
    }

    /**
     * Remove a tag from the contact.
     */
    private function executeRemoveTag(array $data, Contact $contact): void
    {
        $tagId = $data['tag_id'] ?? null;

        if ($tagId) {
            $wasTagged = $contact->tags()->where('tags.id', $tagId)->exists();
            $contact->tags()->detach($tagId);
            Log::info('Drip: tag removed from contact', [
                'contact_id' => $contact->id,
                'tag_id' => $tagId,
            ]);

            if ($wasTagged) {
                $tag = \App\Models\Tag::find($tagId);
                if ($tag) {
                    try { event(new \App\Events\TagRemoved($contact, $tag)); } catch (\Throwable $e) {}
                }
            }
        }
    }

    /**
     * Update a contact field.
     */
    private function executeUpdateField(array $data, Contact $contact): void
    {
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;

        if (! $field) {
            return;
        }

        // Standard fields
        $allowedFields = [
            'first_name', 'last_name', 'company', 'job_title',
            'phone', 'city', 'country', 'timezone', 'lead_score',
        ];

        if (in_array($field, $allowedFields)) {
            $contact->update([$field => $value]);
        } elseif (str_starts_with($field, 'custom_fields.')) {
            // Update custom field within JSON column
            $key = str_replace('custom_fields.', '', $field);
            $customFields = $contact->custom_fields ?? [];
            $customFields[$key] = $value;
            $contact->update(['custom_fields' => $customFields]);
        }

        Log::info('Drip: contact field updated', [
            'contact_id' => $contact->id,
            'field' => $field,
        ]);
    }

    /**
     * Advance the enrollment to the next step, calculating the next execution time.
     */
    public function advanceToNextStep(DripEnrollment $enrollment, ?Collection $steps = null): void
    {
        if (! $steps) {
            $steps = $enrollment->sequence->steps()->orderBy('position')->get();
        }

        $nextStepIndex = $enrollment->current_step + 1;

        if ($nextStepIndex >= $steps->count()) {
            $enrollment->update([
                'current_step' => $nextStepIndex,
                'status' => 'completed',
                'next_step_at' => null,
                'completed_at' => now(),
            ]);

            Log::info('Drip enrollment completed', [
                'enrollment_id' => $enrollment->id,
            ]);

            return;
        }

        $nextStep = $steps[$nextStepIndex];
        $nextStepAt = Carbon::now()->add($nextStep->getDelayInterval());

        $enrollment->update([
            'current_step' => $nextStepIndex,
            'next_step_at' => $nextStepAt,
        ]);

        Log::info('Drip enrollment advanced', [
            'enrollment_id' => $enrollment->id,
            'next_step' => $nextStepIndex,
            'next_step_at' => $nextStepAt->toDateTimeString(),
        ]);
    }
}
