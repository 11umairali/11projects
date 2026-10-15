<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\AI\AIManager;
use App\Services\AI\SentimentAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AIController extends Controller
{
    public function __construct(
        private readonly AIManager $aiManager,
        private readonly SentimentAnalyzer $sentimentAnalyzer,
    ) {}

    /**
     * Generate an AI reply to a message.
     */
    public function generateReply(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:10000',
            'conversation_history' => 'nullable|array',
            'conversation_history.*.role' => 'required_with:conversation_history|string|in:user,assistant',
            'conversation_history.*.content' => 'required_with:conversation_history|string',
            'sender_name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $workspace = Workspace::find($user->active_workspace_id);

        if (!$workspace) {
            return response()->json(['message' => 'Active workspace not found.'], 404);
        }

        $context = [
            'conversation_history' => $request->input('conversation_history', []),
            'sender_name' => $request->input('sender_name'),
            'subject' => $request->input('subject'),
            'agent_name' => $user->name,
        ];

        try {
            $aiResponse = $this->aiManager->generateReply(
                $workspace,
                $request->input('message'),
                $context
            );

            return response()->json([
                'data' => [
                    'reply' => $aiResponse->content,
                    'confidence' => $aiResponse->confidence,
                    'model' => $aiResponse->model,
                    'provider' => $aiResponse->provider,
                    'tokens_in' => $aiResponse->tokens_in,
                    'tokens_out' => $aiResponse->tokens_out,
                    'cost' => $aiResponse->cost,
                    'response_time_ms' => $aiResponse->response_time_ms,
                    'sources_used' => $aiResponse->sources_used,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'AI generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze the sentiment of a text.
     */
    public function analyzeSentiment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $workspaceId = $user->active_workspace_id;

        $result = $this->sentimentAnalyzer->analyze(
            $request->input('text'),
            $workspaceId
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
