<?php

namespace App\Events;

use App\Models\Contact;
use App\Models\Workflow;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Workflow $workflow,
        public readonly ?Contact $contact,
        public readonly array $fields = [],
    ) {}
}
