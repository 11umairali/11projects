<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CannedResponseController extends Controller
{
    /**
     * List canned responses visible to the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceId = $user->active_workspace_id;

        $responses = CannedResponse::forWorkspace($workspaceId, $user->id)
            ->orderBy('usage_count', 'desc')
            ->get();

        return response()->json(['data' => $responses]);
    }

    /**
     * Create a new canned response.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceId = $user->active_workspace_id;

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'shortcut' => 'nullable|string|max:50|alpha_dash',
            'content' => 'required|string|max:5000',
            'scope' => 'nullable|in:personal,team',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check shortcut uniqueness within workspace
        $shortcut = $request->input('shortcut');
        if ($shortcut) {
            $exists = CannedResponse::where('workspace_id', $workspaceId)
                ->where('shortcut', $shortcut)
                ->exists();

            if ($exists) {
                return response()->json([
                    'errors' => ['shortcut' => ['This shortcut is already taken in your workspace.']],
                ], 422);
            }
        }

        $response = CannedResponse::create([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'title' => $request->input('title'),
            'shortcut' => $shortcut ?: null,
            'content' => $request->input('content'),
            'scope' => $request->input('scope', 'personal'),
            'category' => $request->input('category'),
        ]);

        return response()->json(['data' => $response], 201);
    }

    /**
     * Show a single canned response.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $response = CannedResponse::where('workspace_id', $user->active_workspace_id)
            ->findOrFail($id);

        return response()->json(['data' => $response]);
    }

    /**
     * Update a canned response.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $workspaceId = $user->active_workspace_id;

        $response = CannedResponse::where('workspace_id', $workspaceId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'shortcut' => 'nullable|string|max:50|alpha_dash',
            'content' => 'sometimes|required|string|max:5000',
            'scope' => 'nullable|in:personal,team',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check shortcut uniqueness (exclude self)
        $shortcut = $request->input('shortcut');
        if ($shortcut) {
            $exists = CannedResponse::where('workspace_id', $workspaceId)
                ->where('shortcut', $shortcut)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'errors' => ['shortcut' => ['This shortcut is already taken in your workspace.']],
                ], 422);
            }
        }

        $response->update($request->only(['title', 'shortcut', 'content', 'scope', 'category']));

        return response()->json(['data' => $response->fresh()]);
    }

    /**
     * Delete a canned response.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        CannedResponse::where('workspace_id', $user->active_workspace_id)
            ->where('id', $id)
            ->delete();

        return response()->json(['message' => 'Deleted.'], 200);
    }
}
