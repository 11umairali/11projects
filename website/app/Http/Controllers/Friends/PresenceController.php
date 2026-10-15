<?php

namespace App\Http\Controllers\Friends;

use App\Http\Controllers\Controller;
use App\Services\Friends\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Chat privacy switches (website + app): online status / last seen, and read receipts (blue ticks). */
class PresenceController extends Controller
{
    private function data(int $id): array
    {
        $s = app(PresenceService::class);
        return ['enabled' => $s->enabled($id), 'last_seen' => $s->enabled($id), 'read_receipts' => $s->receiptsEnabled($id), 'findable' => $s->findable($id)];
    }

    public function show(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->data($r->user()->id)]);
    }

    public function update(Request $r): JsonResponse
    {
        $s = app(PresenceService::class);
        $id = $r->user()->id;
        foreach (['enabled', 'last_seen'] as $k) {
            if ($r->has($k)) { $s->setEnabled($id, filter_var($r->input($k), FILTER_VALIDATE_BOOLEAN)); break; }
        }
        if ($r->has('findable')) $s->setFindable($id, filter_var($r->input('findable'), FILTER_VALIDATE_BOOLEAN));
        if ($r->has('read_receipts')) $s->setReceipts($id, filter_var($r->input('read_receipts'), FILTER_VALIDATE_BOOLEAN));
        return response()->json(['message' => 'Saved.', 'data' => $this->data($id)]);
    }
}
