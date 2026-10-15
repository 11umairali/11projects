<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

class TagController extends Controller
{
    /**
     * List all tags for the workspace.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaceId = $request->user()->active_workspace_id;

        $tags = Tag::where('workspace_id', $workspaceId)
            ->withCount(['contacts', 'conversations'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return TagResource::collection($tags);
    }

    /**
     * Create a new tag.
     */
    public function store(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->active_workspace_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for duplicate name within workspace
        $exists = Tag::where('workspace_id', $workspaceId)
            ->where('name', $request->input('name'))
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => ['name' => ['A tag with this name already exists.']],
            ], 422);
        }

        $tag = Tag::create([
            'workspace_id' => $workspaceId,
            ...$validator->validated(),
        ]);

        return (new TagResource($tag))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single tag.
     */
    public function show(Request $request, int $id): TagResource|JsonResponse
    {
        $workspaceId = $request->user()->active_workspace_id;

        $tag = Tag::where('workspace_id', $workspaceId)
            ->withCount(['contacts', 'conversations'])
            ->find($id);

        if (!$tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        return new TagResource($tag);
    }

    /**
     * Update a tag.
     */
    public function update(Request $request, int $id): TagResource|JsonResponse
    {
        $workspaceId = $request->user()->active_workspace_id;

        $tag = Tag::where('workspace_id', $workspaceId)->find($id);

        if (!$tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for duplicate name if changing
        if ($request->has('name') && $request->input('name') !== $tag->name) {
            $exists = Tag::where('workspace_id', $workspaceId)
                ->where('name', $request->input('name'))
                ->where('id', '!=', $tag->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'errors' => ['name' => ['A tag with this name already exists.']],
                ], 422);
            }
        }

        $tag->update($validator->validated());

        return new TagResource($tag);
    }

    /**
     * Delete a tag.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $workspaceId = $request->user()->active_workspace_id;

        $tag = Tag::where('workspace_id', $workspaceId)->find($id);

        if (!$tag) {
            return response()->json(['message' => 'Tag not found.'], 404);
        }

        // Detach from all contacts and conversations before deleting
        $tag->contacts()->detach();
        $tag->conversations()->detach();
        $tag->delete();

        return response()->json(null, 204);
    }
}
