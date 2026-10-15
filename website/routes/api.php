<?php

use App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Public auth (no token required) ──────────────────────────────
    Route::prefix('auth')->middleware('throttle:api')->group(function () {
        Route::post('register',         [Api\AuthController::class, 'register'])->middleware('throttle:sensitive');
        Route::post('login',            [Api\AuthController::class, 'login']);
        Route::post('forgot-password',  [Api\AuthController::class, 'forgotPassword'])->middleware('throttle:sensitive');
        Route::post('reset-password',   [Api\AuthController::class, 'resetPassword'])->middleware('throttle:sensitive');
        Route::post('social/{provider}', [Api\AuthController::class, 'socialLogin'])
            ->where('provider', 'google|microsoft|github')
            ->middleware('throttle:sensitive');
    });

    // ── Auth — endpoints that require a Sanctum token ────────────────
    Route::prefix('auth')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('logout', [Api\AuthController::class, 'logout']);
        Route::get('me',      [Api\AuthController::class, 'me']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        // Contacts — require contacts scope
        Route::middleware('ability:contacts')->group(function () {
            Route::apiResource('contacts', Api\ContactController::class);
            Route::post('contacts/import', [Api\ContactController::class, 'import'])->middleware('throttle:sensitive');
            Route::get('contacts/export', [Api\ContactController::class, 'export'])->middleware('throttle:sensitive');
        });

        // Conversations — require conversations scope
        Route::middleware('ability:conversations')->group(function () {
            Route::apiResource('conversations', Api\ConversationController::class);
            Route::post('conversations/{conversation}/reply', [Api\ConversationController::class, 'reply']);
            Route::post('conversations/{conversation}/assign', [Api\ConversationController::class, 'assign']);
            Route::post('conversations/{conversation}/close', [Api\ConversationController::class, 'close']);
        });

        // Tags — require tags scope
        Route::middleware('ability:tags')->group(function () {
            Route::apiResource('tags', Api\TagController::class);
        });

        // Campaigns — require campaigns scope
        Route::middleware('ability:campaigns')->group(function () {
            Route::apiResource('campaigns', Api\CampaignController::class);
            Route::post('campaigns/{campaign}/send', [Api\CampaignController::class, 'send'])->middleware('throttle:sensitive');
        });

        // Workflows — require workflows scope
        Route::middleware('ability:workflows')->group(function () {
            Route::apiResource('workflows', Api\WorkflowController::class);
            Route::post('workflows/{workflow}/activate', [Api\WorkflowController::class, 'activate']);
            Route::post('workflows/{workflow}/pause', [Api\WorkflowController::class, 'pause']);
        });

        // Knowledge Base — require knowledge-base scope
        Route::middleware('ability:knowledge-base')->group(function () {
            Route::apiResource('knowledge-base', Api\KnowledgeBaseController::class);
            Route::post('knowledge-base/scrape', [Api\KnowledgeBaseController::class, 'scrape'])->middleware('throttle:sensitive');
        });

        // Temp Mail
        Route::middleware('ability:temp-mail')->prefix('temp-mail')->group(function () {
            Route::get('domains', [Api\TempMailController::class, 'domains']);
            Route::get('addresses', [Api\TempMailController::class, 'index']);
            Route::post('addresses', [Api\TempMailController::class, 'store']);
            Route::get('addresses/{uuid}', [Api\TempMailController::class, 'show']);
            Route::delete('addresses/{uuid}', [Api\TempMailController::class, 'destroy']);
            Route::get('addresses/{uuid}/messages', [Api\TempMailController::class, 'messages']);
            Route::get('messages/{uuid}', [Api\TempMailController::class, 'readMessage']);
        });

        // AI — require ai scope, stricter rate limits (expensive operations)
        Route::middleware('ability:ai')->group(function () {
            Route::post('ai/generate-reply', [Api\AIController::class, 'generateReply'])->middleware('throttle:sensitive');
            Route::post('ai/analyze-sentiment', [Api\AIController::class, 'analyzeSentiment'])->middleware('throttle:sensitive');
        });

        // Analytics — require analytics scope
        Route::middleware('ability:analytics')->group(function () {
            Route::get('analytics/overview', [Api\AnalyticsController::class, 'overview']);
            Route::get('analytics/ai', [Api\AnalyticsController::class, 'ai']);
            Route::get('analytics/team', [Api\AnalyticsController::class, 'team']);
        });

        // Canned Responses — require canned-responses scope
        Route::middleware('ability:canned-responses')->group(function () {
            Route::apiResource('canned-responses', Api\CannedResponseController::class);
        });

        // User & Workspace — no scope required (read-only, widely accessible)
        Route::get('user', fn (Request $request) => $request->user());
        Route::get('workspace', [Api\WorkspaceController::class, 'show']);
    });

}); // end v1

require __DIR__ . '/api_mobile.php';
