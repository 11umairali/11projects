<?php

namespace App\Services\Integrations;

use App\Models\ChannelIntegration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ZapierIntegrationService
{
    protected Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 120,
            'connect_timeout' => 5,
        ]);
    }

    /**
     * Supported events that can be sent to Zapier.
     */
    public const SUPPORTED_EVENTS = [
        'contact.created',
        'contact.updated',
        'conversation.new',
        'deal.won',
        'deal.lost',
        'deal.stage_changed',
        'campaign.sent',
        'ai.reply.generated',
    ];

    /**
     * Fire a webhook to all active Zapier integrations in a workspace.
     *
     * Zapier works by registering a "catch hook" URL that MailTrixy POSTs to.
     * Each workspace can have one Zapier integration, and the webhook URLs
     * (targets) are stored in the integration's config.webhook_urls array.
     * If no explicit targets are registered, the integration's own webhook
     * endpoint acts as a pass-through.
     *
     * @param int    $workspaceId The workspace ID.
     * @param string $event       The event name (e.g. 'contact.created').
     * @param array  $data        The event payload data.
     */
    public function triggerWebhook(int $workspaceId, string $event, array $data): void
    {
        try {
            $integration = ChannelIntegration::where('workspace_id', $workspaceId)
                ->where('channel', 'zapier')
                ->where('status', 'active')
                ->first();

            if (! $integration) {
                return;
            }

            $config = $integration->config ?? [];
            $webhookUrls = $config['webhook_urls'] ?? [];

            if (empty($webhookUrls)) {
                // No registered Zapier catch hook URLs -- nothing to send to.
                // This is normal: the workspace has Zapier connected (for receiving)
                // but hasn't registered any outgoing trigger URLs yet.
                Log::debug('ZapierIntegration: No webhook URLs registered, skipping trigger', [
                    'workspace_id' => $workspaceId,
                    'event' => $event,
                ]);
                return;
            }

            $payload = [
                'event' => $event,
                'workspace_id' => $workspaceId,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ];

            foreach ($webhookUrls as $url) {
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    Log::warning('ZapierIntegration: Invalid webhook URL skipped', [
                        'workspace_id' => $workspaceId,
                        'url' => $url,
                    ]);
                    continue;
                }

                try {
                    $this->httpClient->post($url, [
                        'json' => $payload,
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'User-Agent' => config('app.name', 'MailTrixy') . '/1.0',
                            'X-MailTrixy-Event' => $event,
                        ],
                    ]);

                    Log::info('ZapierIntegration: Webhook triggered', [
                        'workspace_id' => $workspaceId,
                        'event' => $event,
                        'url' => parse_url($url, PHP_URL_HOST),
                    ]);
                } catch (GuzzleException $e) {
                    Log::error('ZapierIntegration: Webhook delivery failed', [
                        'workspace_id' => $workspaceId,
                        'event' => $event,
                        'url' => parse_url($url, PHP_URL_HOST),
                        'error' => $e->getMessage(),
                    ]);
                    // Continue to next URL -- don't let one failure block others
                }
            }
        } catch (\Throwable $e) {
            Log::error('ZapierIntegration: triggerWebhook failed', [
                'workspace_id' => $workspaceId,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a Zapier catch hook URL for a workspace.
     * Called when a Zap subscribes to MailTrixy triggers.
     *
     * @param int    $workspaceId The workspace ID.
     * @param string $hookUrl     The Zapier catch hook URL.
     * @return bool
     */
    public function registerWebhookUrl(int $workspaceId, string $hookUrl): bool
    {
        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'zapier')
            ->where('status', 'active')
            ->first();

        if (! $integration) {
            return false;
        }

        $config = $integration->config ?? [];
        $urls = $config['webhook_urls'] ?? [];

        if (! in_array($hookUrl, $urls)) {
            $urls[] = $hookUrl;
        }

        $config['webhook_urls'] = $urls;
        $integration->update(['config' => $config]);

        Log::info('ZapierIntegration: Webhook URL registered', [
            'workspace_id' => $workspaceId,
            'url_host' => parse_url($hookUrl, PHP_URL_HOST),
        ]);

        return true;
    }

    /**
     * Unregister a Zapier catch hook URL.
     * Called when a Zap unsubscribes.
     */
    public function unregisterWebhookUrl(int $workspaceId, string $hookUrl): bool
    {
        $integration = ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'zapier')
            ->where('status', 'active')
            ->first();

        if (! $integration) {
            return false;
        }

        $config = $integration->config ?? [];
        $urls = $config['webhook_urls'] ?? [];

        $config['webhook_urls'] = array_values(array_filter($urls, fn ($u) => $u !== $hookUrl));
        $integration->update(['config' => $config]);

        Log::info('ZapierIntegration: Webhook URL unregistered', [
            'workspace_id' => $workspaceId,
        ]);

        return true;
    }

    /**
     * Check if Zapier integration is active for a workspace.
     */
    public function isActive(int $workspaceId): bool
    {
        return ChannelIntegration::where('workspace_id', $workspaceId)
            ->where('channel', 'zapier')
            ->where('status', 'active')
            ->exists();
    }
}
