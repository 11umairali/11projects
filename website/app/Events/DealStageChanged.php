<?php

namespace App\Events;

use App\Models\Deal;
use App\Models\DealStage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealStageChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Deal $deal,
        public readonly DealStage $previousStage,
        public readonly DealStage $newStage,
    ) {}
}
