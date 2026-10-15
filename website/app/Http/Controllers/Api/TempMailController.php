<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\TempMailAddress;
use App\Models\TempMailDomain;
use App\Services\TempMailService;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TempMailController extends Controller
{
    use AuthorizesApiActions;

    public function domains(Request $request): JsonResponse
    {
        $wsId = $this->workspaceId($request);

        $domains = TempMailDomain::where('status', 'active')
            ->where('workspace_id', $wsId)
            ->get(['id', 'uuid', 'domain', 'display_name', 'default_lifetime_hours']);

        return response()->json(['data' => $domains]);
    }

    public function index(Request $request): JsonResponse
    {
        $wsId = $this->workspaceId($request);
        $addresses = TempMailAddress::where('workspace_id', $wsId)
            ->active()
            ->latest()
            ->get(['id', 'uuid', 'full_address', 'label', 'messages_count', 'expires_at', 'created_at']);

        return response()->json(['data' => $addresses]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;

        $request->validate([
            'domain_id' => 'nullable|integer|exists:temp_mail_domains,id',
            'label' => 'nullable|string|max:255',
        ]);

        try {
            $service = app(TempMailService::class);
            $address = $service->generateAddress(
                $request->user()->activeWorkspace,
                $request->user(),
                $request->input('domain_id'),
                $request->input('label'),
            );

            return response()->json([
                'data' => [
                    'uuid' => $address->uuid,
                    'address' => $address->full_address,
                    'label' => $address->label,
                    'expires_at' => $address->expires_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $wsId = $this->workspaceId($request);
        $address = TempMailAddress::where('workspace_id', $wsId)->where('uuid', $uuid)->firstOrFail();

        return response()->json(['data' => $address]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $wsId = $this->workspaceId($request);
        $address = TempMailAddress::where('workspace_id', $wsId)->where('uuid', $uuid)->firstOrFail();

        app(TempMailService::class)->deleteAddress($address);

        return response()->json(['message' => 'Address deleted.']);
    }

    public function messages(Request $request, string $uuid): JsonResponse
    {
        $wsId = $this->workspaceId($request);
        $address = TempMailAddress::where('workspace_id', $wsId)->where('uuid', $uuid)->firstOrFail();

        $messages = Message::where('conversation_id', $address->conversation_id)
            ->latest()
            ->get(['id', 'uuid', 'from_email', 'from_name', 'subject', 'body_text', 'created_at']);

        return response()->json(['data' => $messages]);
    }

    public function readMessage(Request $request, string $uuid): JsonResponse
    {
        $wsId = $this->workspaceId($request);
        $message = Message::where('workspace_id', $wsId)->where('uuid', $uuid)->with('attachments')->firstOrFail();

        return response()->json(['data' => $message]);
    }

    private function workspaceId(Request $request): int
    {
        return $request->user()->active_workspace_id;
    }
}
