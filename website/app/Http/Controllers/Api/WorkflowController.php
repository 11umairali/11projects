<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkflowResource;
use App\Models\Workflow;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class WorkflowController extends Controller
{
    use AuthorizesApiActions;
    /**
     * List workflows with filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $query = Workflow::where('workspace_id', $workspaceId)
            ->with('createdBy')
            ->withCount('workflowNodes');

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Search by name
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['name', 'status', 'executions_count', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 25), 100);

        return WorkflowResource::collection($query->paginate($perPage));
    }

    /**
     * Show a single workflow with its canvas data.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $workflow = Workflow::where('workspace_id', $workspaceId)
            ->with(['createdBy', 'workflowNodes', 'workflowEdges'])
            ->withCount('workflowNodes')
            ->find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found.'], 404);
        }

        $resource = new WorkflowResource($workflow);
        $resource->additional([
            'canvas_data' => $workflow->canvas_data,
            'nodes' => $workflow->workflowNodes->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'config' => $n->config,
                'position_x' => $n->position_x,
                'position_y' => $n->position_y,
            ]),
            'edges' => $workflow->workflowEdges->map(fn ($e) => [
                'id' => $e->id,
                'source_node_id' => $e->source_node_id,
                'target_node_id' => $e->target_node_id,
                'condition' => $e->condition,
            ]),
        ]);

        return $resource->response();
    }

    /**
     * Create a new workflow.
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        // Enforce plan limit on workflows
        if ($deny = $this->denyUnlessPlanAllows($request, 'workflows')) return $deny;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'canvas_data' => 'nullable|array',
        ], [
            'name.required' => 'Please give your workflow a name.',
            'name.max' => 'Workflow name is too long (max 255 characters).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $workflow = Workflow::create([
            'workspace_id' => $workspaceId,
            'created_by' => $request->user()->id,
            'status' => 'draft',
            ...$validator->validated(),
        ]);

        $workflow->load('createdBy');

        return (new WorkflowResource($workflow))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a workflow.
     */
    public function update(Request $request, int $id): WorkflowResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $workflow = Workflow::where('workspace_id', $workspaceId)->find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found.'], 404);
        }

        // FIX-030: Validate canvas_data structure to prevent engine crashes
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'canvas_data' => 'nullable|array',
            'canvas_data.nodes' => 'nullable|array|max:200',
            'canvas_data.nodes.*.id' => 'required_with:canvas_data.nodes|string|max:100',
            'canvas_data.nodes.*.type' => 'required_with:canvas_data.nodes|string|in:trigger,condition,action',
            'canvas_data.nodes.*.subtype' => 'required_with:canvas_data.nodes|string|max:50',
            'canvas_data.nodes.*.config' => 'nullable|array',
            'canvas_data.edges' => 'nullable|array|max:500',
            'canvas_data.edges.*.source' => 'required_with:canvas_data.edges|string|max:100',
            'canvas_data.edges.*.target' => 'required_with:canvas_data.edges|string|max:100',
        ], [
            'name.required' => 'Please give your workflow a name.',
            'name.max' => 'Workflow name is too long (max 255 characters).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Increment version if canvas data changed
        if (isset($data['canvas_data'])) {
            $data['version'] = $workflow->version + 1;
        }

        $workflow->update($data);
        $workflow->load('createdBy');

        return new WorkflowResource($workflow);
    }

    /**
     * Delete a workflow (soft delete).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $workflow = Workflow::where('workspace_id', $workspaceId)->find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found.'], 404);
        }

        if ($workflow->status === 'active') {
            return response()->json([
                'message' => 'Deactivate the workflow before deleting it.',
            ], 422);
        }

        $workflow->delete();

        return response()->json(null, 204);
    }

    /**
     * Activate a workflow.
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $workflow = Workflow::where('workspace_id', $workspaceId)->find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found.'], 404);
        }

        if ($workflow->status === 'active') {
            return response()->json(['message' => 'Workflow is already active.'], 422);
        }

        $workflow->update(['status' => 'active']);
        $workflow->load('createdBy');

        return response()->json([
            'data' => new WorkflowResource($workflow),
            'message' => 'Workflow activated.',
        ]);
    }

    /**
     * Pause a workflow.
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $workflow = Workflow::where('workspace_id', $workspaceId)->find($id);

        if (!$workflow) {
            return response()->json(['message' => 'Workflow not found.'], 404);
        }

        if ($workflow->status !== 'active') {
            return response()->json(['message' => 'Only active workflows can be paused.'], 422);
        }

        $workflow->update(['status' => 'paused']);
        $workflow->load('createdBy');

        return response()->json([
            'data' => new WorkflowResource($workflow),
            'message' => 'Workflow paused.',
        ]);
    }
}
