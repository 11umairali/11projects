<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\Campaign\CampaignService;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    use AuthorizesApiActions;

    public function __construct(
        private readonly CampaignService $campaignService,
    ) {}

    /**
     * List campaigns with filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $query = Campaign::where('workspace_id', $workspaceId)
            ->with('createdBy');

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Search by name or subject
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['name', 'status', 'recipients_count', 'created_at', 'sent_at', 'scheduled_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 25), 100);

        return CampaignResource::collection($query->paginate($perPage));
    }

    /**
     * Show a single campaign.
     */
    public function show(Request $request, int $id): CampaignResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $campaign = Campaign::where('workspace_id', $workspaceId)
            ->with('createdBy')
            ->find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found.'], 404);
        }

        return new CampaignResource($campaign);
    }

    /**
     * Create a new campaign.
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        // Enforce plan limit on campaigns per month
        if ($deny = $this->denyUnlessPlanAllows($request, 'campaigns_per_month')) return $deny;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:broadcast,drip,ab_test',
            'subject' => 'nullable|string|max:500',
            'body_html' => 'nullable|string',
            'body_json' => 'nullable|array',
            'preview_text' => 'nullable|string|max:255',
            'audience_type' => 'nullable|string|in:all,segment,list',
            'audience_id' => 'nullable|integer',
            'email_account_id' => ['nullable', 'integer', Rule::exists('email_accounts', 'id')->where('workspace_id', $workspaceId)],
            'scheduled_at' => 'nullable|date|after:now',
        ], [
            'name.required' => 'Please give your campaign a name.',
            'name.max' => 'Campaign name is too long (max 255 characters).',
            'subject.max' => 'Subject line is too long (max 500 characters).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $campaign = Campaign::create([
            'workspace_id' => $workspaceId,
            'created_by' => $request->user()->id,
            'status' => 'draft',
            ...$validator->validated(),
        ]);

        $campaign->load('createdBy');

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a campaign.
     */
    public function update(Request $request, int $id): CampaignResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $campaign = Campaign::where('workspace_id', $workspaceId)->find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found.'], 404);
        }

        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return response()->json([
                'message' => 'Only draft or scheduled campaigns can be edited.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:broadcast,drip,ab_test',
            'subject' => 'nullable|string|max:500',
            'body_html' => 'nullable|string',
            'body_json' => 'nullable|array',
            'preview_text' => 'nullable|string|max:255',
            'audience_type' => 'nullable|string|in:all,segment,list',
            'audience_id' => 'nullable|integer',
            'email_account_id' => ['nullable', 'integer', Rule::exists('email_accounts', 'id')->where('workspace_id', $workspaceId)],
            'scheduled_at' => 'nullable|date|after:now',
        ], [
            'name.required' => 'Please give your campaign a name.',
            'name.max' => 'Campaign name is too long (max 255 characters).',
            'subject.max' => 'Subject line is too long (max 500 characters).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $campaign->update($validator->validated());
        $campaign->load('createdBy');

        return new CampaignResource($campaign);
    }

    /**
     * Delete a campaign.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $campaign = Campaign::where('workspace_id', $workspaceId)->find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found.'], 404);
        }

        if ($campaign->status === 'sending') {
            return response()->json([
                'message' => 'Cannot delete a campaign that is currently sending.',
            ], 422);
        }

        $campaign->delete();

        return response()->json(null, 204);
    }

    /**
     * Send a campaign.
     */
    public function send(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $campaign = Campaign::where('workspace_id', $workspaceId)->find($id);

        if (!$campaign) {
            return response()->json(['message' => 'Campaign not found.'], 404);
        }

        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return response()->json([
                'message' => "Campaign cannot be sent — current status: {$campaign->status}.",
            ], 422);
        }

        try {
            $this->campaignService->sendCampaign($campaign);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $campaign->refresh();
        $campaign->load('createdBy');

        return response()->json([
            'data' => new CampaignResource($campaign),
            'message' => 'Campaign sending has started.',
        ]);
    }
}
