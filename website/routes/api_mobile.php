<?php

/**
 * Mobile-app API (Flutter). Loaded from routes/api.php:
 *
 *     require __DIR__ . '/api_mobile.php';
 *
 * Everything lives under /api/v1 and requires a Sanctum bearer token
 * (obtained from POST /api/v1/auth/login). Workspace roles are enforced
 * inside each controller with AuthorizesApiActions::denyUnlessRole().
 */

use App\Http\Controllers\Api\Mobile;
use App\Http\Controllers\InboxApiController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// ── E-mail images for the app: signed URLs (no login token possible from the HTML renderer) ─────
Route::prefix('v1/email-asset')->middleware('signed:relative')->group(function () {
    Route::get('inline/{id}', [Mobile\EmailAssetController::class, 'inline'])->name('mobile.email.inline');
    Route::get('proxy',       [Mobile\EmailAssetController::class, 'proxy'])->name('mobile.email.proxy');
});

// ── Public (no token): connectivity check + in-app account recovery ──────────────────────────────
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('ping',                          [Mobile\AuthExtrasController::class, 'ping']);
    Route::get('auth/username-available',       [Mobile\AuthExtrasController::class, 'usernameAvailable']);
    Route::get('auth/phone-config',             [Mobile\AuthExtrasController::class, 'phoneConfig']);
    Route::post('auth/recover/phrase',          [Mobile\AuthExtrasController::class, 'recoverWithPhrase'])->middleware('throttle:sensitive');
    Route::post('auth/password/code',           [Mobile\AuthExtrasController::class, 'sendCode'])->middleware('throttle:sensitive');
    Route::post('auth/password/reset-code',     [Mobile\AuthExtrasController::class, 'resetWithCode'])->middleware('throttle:sensitive');
});

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::get('realtime/config', [Mobile\RealtimeController::class, 'config']);
    Route::post('realtime/auth', [Mobile\RealtimeController::class, 'auth']);

    // ── Account ─────────────────────────────────────────────────────────
    Route::get('me',                       [Mobile\MeController::class, 'show']);
    Route::post('me',                      [Mobile\MeController::class, 'update']);          // multipart (avatar) – POST for PHP multipart
    Route::delete('me/avatar',             [Mobile\MeController::class, 'removeAvatar']);
    Route::post('me/password',             [Mobile\MeController::class, 'password'])->middleware('throttle:sensitive');
    Route::post('me/2fa/setup',            [Mobile\MeController::class, 'twoFactorSetup']);
    Route::post('me/2fa/enable',           [Mobile\MeController::class, 'twoFactorEnable'])->middleware('throttle:sensitive');
    Route::post('me/2fa/disable',          [Mobile\MeController::class, 'twoFactorDisable'])->middleware('throttle:sensitive');
    Route::get('me/sessions',              [Mobile\MeController::class, 'sessions']);
    Route::delete('me/sessions/others',    [Mobile\MeController::class, 'revokeOtherSessions']);
    Route::delete('me/sessions/{id}',      [Mobile\MeController::class, 'revokeSession']);
    Route::get('me/security-log',          [Mobile\MeController::class, 'securityLog']);
    Route::get('me/notification-preferences',  [Mobile\MeController::class, 'notificationPrefs']);
    Route::put('me/notification-preferences',  [Mobile\MeController::class, 'updateNotificationPrefs']);
    Route::post('me/devices/check-push', [Mobile\MeController::class, 'checkPush'])->middleware('throttle:3,1');
    Route::post('me/devices',              [Mobile\MeController::class, 'registerDevice']);
    Route::delete('me/devices',            [Mobile\MeController::class, 'unregisterDevice']);
    Route::post('me/devices/remove',       [Mobile\MeController::class, 'unregisterDevice']);
    Route::get('me/export',                [Mobile\MeController::class, 'export'])->middleware('throttle:sensitive');
    Route::post('me/deactivate',           [Mobile\MeController::class, 'deactivate'])->middleware('throttle:sensitive');
    Route::post('me/delete',               [Mobile\MeController::class, 'deleteAccount'])->middleware('throttle:sensitive');

    // Sign-in hand-off to the web (checkout / OAuth connect flows)
    Route::post('auth/web-link',           [Mobile\BillingController::class, 'webLink'])->middleware('throttle:sensitive');

    // ── Notifications (in-app bell) ─────────────────────────────────────
    Route::get('notifications',            [NotificationController::class, 'index']);
    Route::post('notifications/read',      [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

    // ── Inbox (same controller the web inbox uses) ──────────────────────
    Route::prefix('inbox')->group(function () {
        Route::get('conversations',                        [InboxApiController::class, 'conversations']);
        Route::get('conversations/{id}/messages',          [InboxApiController::class, 'messages']);
        Route::post('conversations/{id}/action',           [InboxApiController::class, 'action']);
        Route::post('conversations/{id}/summarize',        [InboxApiController::class, 'summarize']);
        Route::post('conversations/{id}/ai-chat',          [InboxApiController::class, 'aiChat']);
        Route::post('conversations/{id}/fetch-bodies',     [InboxApiController::class, 'fetchBodies']);
        Route::post('bulk',                                [InboxApiController::class, 'bulk']);
        Route::get('poll',                                 [InboxApiController::class, 'poll']);
        Route::get('sidebar',                              [InboxApiController::class, 'sidebar']);
        Route::get('tags',                                 [InboxApiController::class, 'tags']);
        Route::get('team',                                 [InboxApiController::class, 'team']);
        Route::get('accounts',                             [InboxApiController::class, 'accounts']);
        Route::post('sync',                                [InboxApiController::class, 'syncNow']);
        Route::get('messages/{id}/body',                   [Mobile\InboxExtrasController::class, 'messageBody']);
    });

    // ── Workspaces ──────────────────────────────────────────────────────
    Route::get('workspaces',                       [Mobile\WorkspacesController::class, 'index']);
    Route::post('workspaces',                      [Mobile\WorkspacesController::class, 'store']);
    Route::post('workspaces/{id}/switch',          [Mobile\WorkspacesController::class, 'switch']);
    Route::get('workspace/settings',               [Mobile\WorkspacesController::class, 'show']);
    Route::post('workspace/settings',              [Mobile\WorkspacesController::class, 'update']);   // multipart (logo)
    Route::post('workspace/delete',                [Mobile\WorkspacesController::class, 'destroy'])->middleware('throttle:sensitive');
    Route::get('workspace/privacy',                [Mobile\WorkspacesController::class, 'privacy']);
    Route::put('workspace/privacy',                [Mobile\WorkspacesController::class, 'updatePrivacy']);
    Route::get('workspace/contact-settings',       [Mobile\WorkspacesController::class, 'contactSettings']);
    Route::put('workspace/contact-settings',       [Mobile\WorkspacesController::class, 'updateContactSettings']);

    // ── Team ────────────────────────────────────────────────────────────
    Route::get('team',                             [Mobile\TeamController::class, 'index']);
    Route::post('team/invites',                    [Mobile\TeamController::class, 'invite'])->middleware('throttle:sensitive');
    Route::post('team/invites/{id}/resend',        [Mobile\TeamController::class, 'resendInvite'])->middleware('throttle:sensitive');
    Route::delete('team/invites/{id}',             [Mobile\TeamController::class, 'cancelInvite']);
    Route::put('team/members/{userId}/role',       [Mobile\TeamController::class, 'changeRole']);
    Route::delete('team/members/{userId}',         [Mobile\TeamController::class, 'remove']);

    // ── Billing ─────────────────────────────────────────────────────────
    Route::get('checkout/gateways',                [Mobile\CheckoutController::class, 'gateways'])->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::post('checkout/coupon',                 [Mobile\CheckoutController::class, 'coupon'])->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::post('checkout',                        [Mobile\CheckoutController::class, 'start'])->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::post('checkout/{id}/confirm',           [Mobile\CheckoutController::class, 'confirm'])->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::get('checkout/{id}/status',             [Mobile\CheckoutController::class, 'status'])->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::get('payments',                         [Mobile\CheckoutController::class, 'payments'])->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);
    Route::get('billing',                          [Mobile\BillingController::class, 'overview'])->middleware(\App\Http\Middleware\EnsureBillingEnabled::class);

    // ── Deals & pipelines ───────────────────────────────────────────────
    Route::get('pipelines',                        [Mobile\DealsController::class, 'pipelines']);
    Route::post('pipelines',                       [Mobile\DealsController::class, 'createPipeline']);
    Route::delete('pipelines/{id}',                [Mobile\DealsController::class, 'deletePipeline']);
    Route::get('deals',                            [Mobile\DealsController::class, 'index']);
    Route::post('deals',                           [Mobile\DealsController::class, 'store']);
    Route::put('deals/{id}',                       [Mobile\DealsController::class, 'update']);
    Route::post('deals/{id}/move',                 [Mobile\DealsController::class, 'move']);
    Route::post('deals/{id}/won',                  [Mobile\DealsController::class, 'won']);
    Route::post('deals/{id}/lost',                 [Mobile\DealsController::class, 'lost']);
    Route::delete('deals/{id}',                    [Mobile\DealsController::class, 'destroy']);

    // ── Contact extras (prefixes chosen to avoid clashing with contacts/{contact}) ──
    Route::get('contact-groups',                                  [Mobile\ContactExtrasController::class, 'groups']);
    Route::post('contact-groups',                                 [Mobile\ContactExtrasController::class, 'createGroup']);
    Route::put('contact-groups/{id}',                             [Mobile\ContactExtrasController::class, 'updateGroup']);
    Route::delete('contact-groups/{id}',                          [Mobile\ContactExtrasController::class, 'deleteGroup']);
    Route::get('contact-groups/{id}/members',                     [Mobile\ContactExtrasController::class, 'groupMembers']);
    Route::post('contact-groups/{id}/members',                    [Mobile\ContactExtrasController::class, 'addToGroup']);
    Route::delete('contact-groups/{id}/members/{contactId}',      [Mobile\ContactExtrasController::class, 'removeFromGroup']);
    Route::get('contact-trash',                                   [Mobile\ContactExtrasController::class, 'trash']);
    Route::post('contact-trash/restore-all',                      [Mobile\ContactExtrasController::class, 'restoreAll']);
    Route::delete('contact-trash',                                [Mobile\ContactExtrasController::class, 'emptyTrash']);
    Route::post('contact-trash/{id}/restore',                     [Mobile\ContactExtrasController::class, 'restore']);
    Route::delete('contact-trash/{id}',                           [Mobile\ContactExtrasController::class, 'forceDelete']);
    Route::get('contact-duplicates',                              [Mobile\ContactExtrasController::class, 'duplicates']);
    Route::post('contact-merge',                                  [Mobile\ContactExtrasController::class, 'merge']);
    Route::post('contact-bulk',                                   [Mobile\ContactExtrasController::class, 'bulk']);
    Route::get('contact-timeline/{id}',                           [Mobile\ContactExtrasController::class, 'timeline']);

    // ── Settings resources ──────────────────────────────────────────────
    Route::get('auto-reply-rules',                 [Mobile\SettingsController::class, 'rules']);
    Route::post('auto-reply-rules',                [Mobile\SettingsController::class, 'storeRule']);
    Route::post('auto-reply-rules/test',           [Mobile\SettingsController::class, 'testRule']);
    Route::put('auto-reply-rules/{id}',            [Mobile\SettingsController::class, 'updateRule']);
    Route::post('auto-reply-rules/{id}/toggle',    [Mobile\SettingsController::class, 'toggleRule']);
    Route::delete('auto-reply-rules/{id}',         [Mobile\SettingsController::class, 'destroyRule']);

    // Tools that exist on the website but had no app equivalent
    // Friends & phone discovery
    Route::get('friends',                               [Mobile\FriendsController::class, 'overview']);
    Route::post('friends/requests',                     [Mobile\FriendsController::class, 'send']);
    Route::post('friends/requests/{id}/{action}',       [Mobile\FriendsController::class, 'respond']);
    Route::delete('friends/requests/{id}',              [Mobile\FriendsController::class, 'cancel']);
    Route::delete('friends/{userId}',                   [Mobile\FriendsController::class, 'remove']);
    Route::post('friends/suggestions/{userId}/dismiss', [Mobile\FriendsController::class, 'dismiss']);
    Route::post('me/phone',                             [Mobile\FriendsController::class, 'phoneStart'])->middleware('throttle:sensitive');
    Route::post('me/phone/verify',                      [Mobile\FriendsController::class, 'phoneVerify'])->middleware('throttle:sensitive');
    Route::delete('me/phone',                           [Mobile\FriendsController::class, 'phoneRemove']);
    Route::put('me/phone/discoverable',                 [Mobile\FriendsController::class, 'discoverable']);
    Route::post('friends/sync-contacts', [\App\Http\Controllers\Api\Mobile\FriendsController::class, 'syncContacts'])->middleware('throttle:sensitive');
    Route::get('friends/{userId}/messages', [\App\Http\Controllers\Friends\FriendChatController::class, 'messages'])->whereNumber('userId');
    Route::post('friends/{userId}/read', [\App\Http\Controllers\Friends\FriendChatController::class, 'read'])->whereNumber('userId');
    Route::post('friends/{userId}/messages', [\App\Http\Controllers\Friends\FriendChatController::class, 'send'])->whereNumber('userId');
    Route::get('friends/messages/{messageId}/file', [\App\Http\Controllers\Friends\FriendChatController::class, 'file'])->whereNumber('messageId');
    Route::post('friends/{userId}/call', [\App\Http\Controllers\Friends\FriendCallController::class, 'start'])->whereNumber('userId');
    Route::match(['put', 'patch'], 'friends/{userId}/messages/{messageId}', [\App\Http\Controllers\Friends\FriendChatController::class, 'edit'])->whereNumber('userId')->whereNumber('messageId');
    Route::delete('friends/{userId}/messages/{messageId}', [\App\Http\Controllers\Friends\FriendChatController::class, 'delete'])->whereNumber('userId')->whereNumber('messageId');
    Route::post('friends/{userId}/video-call', [\App\Http\Controllers\Friends\FriendCallController::class, 'startVideo'])->whereNumber('userId');
    Route::post('friends/{userId}/messages/{messageId}/react', [\App\Http\Controllers\Friends\FriendChatController::class, 'react'])->whereNumber('userId')->whereNumber('messageId');
    Route::post('friends/{userId}/messages/{messageId}/forward', [\App\Http\Controllers\Friends\FriendChatController::class, 'forward'])->whereNumber('userId')->whereNumber('messageId');
    Route::get('friends/forward-targets', [\App\Http\Controllers\Friends\FriendChatController::class, 'targets']);
    Route::post('friends/chats/organize', [\App\Http\Controllers\Friends\FriendChatController::class, 'organize']);
    Route::get('friends/chats', [\App\Http\Controllers\Friends\FriendChatController::class, 'chats']);
    Route::get('friends/search', [\App\Http\Controllers\Friends\FriendSearchController::class, 'search']);
    Route::delete('calls/history', [\App\Http\Controllers\Friends\FriendCallController::class, 'clearHistory']);
    Route::get('meetings', [\App\Http\Controllers\Meetings\MeetingController::class, 'list']);
    Route::post('meetings', [\App\Http\Controllers\Meetings\MeetingController::class, 'create']);
    Route::get('meetings/{code}', [\App\Http\Controllers\Meetings\MeetingController::class, 'show']);
    Route::post('meetings/{code}/join', [\App\Http\Controllers\Meetings\MeetingController::class, 'join']);
    Route::get('meetings/{code}/poll', [\App\Http\Controllers\Meetings\MeetingController::class, 'poll']);
    Route::post('meetings/{code}/signal', [\App\Http\Controllers\Meetings\MeetingController::class, 'signal']);
    Route::post('meetings/{code}/state', [\App\Http\Controllers\Meetings\MeetingController::class, 'state']);
    Route::post('meetings/{code}/chat', [\App\Http\Controllers\Meetings\MeetingController::class, 'chat']);
    Route::post('meetings/{code}/host', [\App\Http\Controllers\Meetings\MeetingController::class, 'host']);
    Route::post('meetings/{code}/leave', [\App\Http\Controllers\Meetings\MeetingController::class, 'leave']);
    Route::get('meetings/{code}/invitable', [\App\Http\Controllers\Meetings\MeetingController::class, 'invitable']);
    Route::post('meetings/{code}/invite', [\App\Http\Controllers\Meetings\MeetingController::class, 'invite']);
    Route::match(['put', 'patch'], 'meetings/{code}', [\App\Http\Controllers\Meetings\MeetingController::class, 'update']);
    Route::get('presence', [\App\Http\Controllers\Friends\PresenceController::class, 'show']);
    Route::post('presence', [\App\Http\Controllers\Friends\PresenceController::class, 'update']);
    Route::delete('meetings/{code}', [\App\Http\Controllers\Meetings\MeetingController::class, 'cancel']);
    Route::delete('friends/{userId}/messages', [\App\Http\Controllers\Friends\FriendChatController::class, 'clear'])->whereNumber('userId');
    Route::get('people', [\App\Http\Controllers\Friends\PeopleController::class, 'index']);
    Route::get('people/{userId}', [\App\Http\Controllers\Friends\PeopleController::class, 'show'])->whereNumber('userId');
    Route::get('calls/poll', [\App\Http\Controllers\Friends\FriendCallController::class, 'poll']);
    Route::get('calls/history', [\App\Http\Controllers\Friends\FriendCallController::class, 'history']);
    Route::get('calls/{id}/signals', [\App\Http\Controllers\Friends\FriendCallController::class, 'signals'])->whereNumber('id');
    Route::post('calls/{id}/signal', [\App\Http\Controllers\Friends\FriendCallController::class, 'signal'])->whereNumber('id');
    Route::post('calls/{id}/answer', [\App\Http\Controllers\Friends\FriendCallController::class, 'answer'])->whereNumber('id');
    Route::post('calls/{id}/decline', [\App\Http\Controllers\Friends\FriendCallController::class, 'decline'])->whereNumber('id');
    Route::post('calls/{id}/end', [\App\Http\Controllers\Friends\FriendCallController::class, 'end'])->whereNumber('id');
    Route::post('meetings/{code}/recording/start', [\App\Http\Controllers\Meetings\MeetingController::class, 'recStart']);
    Route::post('meetings/{code}/recording/respond', [\App\Http\Controllers\Meetings\MeetingController::class, 'recRespond']);
    Route::post('meetings/{code}/recording/stop', [\App\Http\Controllers\Meetings\MeetingController::class, 'recStop']);
    Route::post('meetings/{code}/recording/claim', [\App\Http\Controllers\Meetings\MeetingController::class, 'recClaim']);
    Route::post('meetings/{code}/recording/upload', [\App\Http\Controllers\Meetings\MeetingController::class, 'recUpload'])->middleware('throttle:20,1');
    Route::get('meetings/recordings/{id}/file', [\App\Http\Controllers\Meetings\MeetingRecordingController::class, 'file'])->whereNumber('id');
    Route::get('email-accounts/mail-settings',     [Mobile\ToolsController::class, 'mailSettings']);
    Route::get('email-accounts/deliverability',    [Mobile\ToolsController::class, 'deliverabilityDefault']);
    Route::post('email-accounts/deliverability',   [Mobile\ToolsController::class, 'deliverabilityCheck'])->middleware('throttle:sensitive');
    Route::get('dashboard/insights',               [Mobile\ToolsController::class, 'insights']);
    Route::post('dashboard/insights/{id}/dismiss', [Mobile\ToolsController::class, 'dismissInsight']);
    Route::get('email-accounts',                   [Mobile\SettingsController::class, 'accounts']);
    Route::post('email-accounts',                  [Mobile\SettingsController::class, 'storeAccount']);
    Route::post('email-accounts/test',             [Mobile\SettingsController::class, 'testAccount'])->middleware('throttle:sensitive');
    Route::put('email-accounts/{id}',              [Mobile\SettingsController::class, 'updateAccount']);
    Route::post('email-accounts/{id}/{action}',    [Mobile\SettingsController::class, 'accountAction'])->whereIn('action', ['disconnect', 'reconnect', 'default', 'toggle-ai']);
    Route::delete('email-accounts/{id}',           [Mobile\SettingsController::class, 'destroyAccount']);
    Route::get('email-accounts/{accountId}/signatures',            [Mobile\SettingsController::class, 'signatures']);
    Route::post('email-accounts/{accountId}/signatures',           [Mobile\SettingsController::class, 'storeSignature']);
    Route::put('email-accounts/{accountId}/signatures/{id}',       [Mobile\SettingsController::class, 'updateSignature']);
    Route::delete('email-accounts/{accountId}/signatures/{id}',    [Mobile\SettingsController::class, 'destroySignature']);

    Route::get('channels',                         [Mobile\SettingsController::class, 'channels']);
    Route::put('channels/{channel}',               [Mobile\SettingsController::class, 'saveChannel']);
    Route::post('channels/{channel}/disconnect',   [Mobile\SettingsController::class, 'disconnectChannel']);

    Route::get('ai-config',                        [Mobile\SettingsController::class, 'ai']);
    Route::put('ai-config',                        [Mobile\SettingsController::class, 'updateAi']);
    Route::post('ai-config/test',                  [Mobile\SettingsController::class, 'testAi'])->middleware('throttle:sensitive');

    Route::get('webhook-logs',                     [Mobile\SettingsController::class, 'webhookLogs']);
    Route::get('webhook-logs/{id}',                [Mobile\SettingsController::class, 'webhookLog']);
    Route::post('webhook-logs/{id}/retry',         [Mobile\SettingsController::class, 'retryWebhook']);

    Route::get('integrations',                     [Mobile\SettingsController::class, 'integrations']);
    Route::post('integrations/{service}/disconnect', [Mobile\SettingsController::class, 'disconnectIntegration']);

    // ── Workflows (step editor + logs) & campaigns (report) ─────────────
    Route::get('workflow-catalog',                 [Mobile\AutomationController::class, 'catalog']);
    Route::get('workflows/{id}/steps',             [Mobile\AutomationController::class, 'steps']);
    Route::put('workflows/{id}/steps',             [Mobile\AutomationController::class, 'saveSteps']);   // id 0 creates
    Route::get('workflows/{id}/executions',        [Mobile\AutomationController::class, 'executions']);
    Route::get('workflow-executions/{id}',         [Mobile\AutomationController::class, 'execution']);
    Route::get('campaign-audiences',               [Mobile\AutomationController::class, 'audiences']);
    Route::post('campaign-save',                   [Mobile\AutomationController::class, 'saveCampaign']);
    Route::put('campaign-save/{id}',               [Mobile\AutomationController::class, 'saveCampaign']);
    Route::get('campaigns/{id}/content',           [Mobile\AutomationController::class, 'campaignContent']);
    Route::get('campaigns/{id}/report',            [Mobile\AutomationController::class, 'report']);
    Route::get('campaigns/{id}/recipients',        [Mobile\AutomationController::class, 'recipients']);

    // ── Content ─────────────────────────────────────────────────────────
    Route::get('activity',                         [Mobile\ContentController::class, 'activity']);
    Route::get('help/articles',                    [Mobile\ContentController::class, 'helpArticles']);
    Route::get('help/articles/{id}',               [Mobile\ContentController::class, 'helpArticle']);
    Route::post('help/articles/{id}/vote',         [Mobile\ContentController::class, 'helpVote']);
    Route::get('email-templates',                  [Mobile\ContentController::class, 'templates']);
    Route::delete('email-templates/{id}',          [Mobile\ContentController::class, 'deleteTemplate']);

    // ── Quick replies & tags ────────────────────────────────────────────
    // Real sending (the stock POST /conversations + /reply only store rows)
    Route::get('inbox/attachments/{id}',           [Mobile\SendController::class, 'attachment']);
    Route::post('inbox/send',                      [Mobile\SendController::class, 'compose']);
    Route::post('inbox/conversations/{id}/send',   [Mobile\SendController::class, 'reply']);
    Route::get('temp-mail-domains',                [Mobile\ContentController::class, 'tempMailDomains']);
    Route::get('quick-replies',                    [Mobile\InboxExtrasController::class, 'quickReplies']);
    Route::post('quick-replies',                   [Mobile\InboxExtrasController::class, 'storeQuickReply']);
    Route::put('quick-replies/{id}',               [Mobile\InboxExtrasController::class, 'updateQuickReply']);
    Route::delete('quick-replies/{id}',            [Mobile\InboxExtrasController::class, 'destroyQuickReply']);
    Route::post('tag-create',                      [Mobile\InboxExtrasController::class, 'storeTag']);
    Route::post('contact-tags/{contactId}',        [Mobile\InboxExtrasController::class, 'attachTag']);
    Route::delete('contact-tags/{contactId}/{tagId}', [Mobile\InboxExtrasController::class, 'detachTag']);
});
