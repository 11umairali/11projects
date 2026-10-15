<?php

namespace App\Services\AI;

use App\Models\AiChannelConfig;
use App\Models\AiConfig;
use App\Models\Workspace;

/**
 * Channel-aware AI config resolver.
 *
 * The application has TWO config layers:
 *
 *   - ai_configs            (one per workspace)
 *       Owns provider / model / API key / monthly cost cap. Billing &
 *       provider concerns ONLY.
 *
 *   - ai_channel_configs    (one per workspace per channel)
 *       Owns behavior — prompt, send_mode, confidence, escalation,
 *       skip filters, reply delay, etc.
 *
 * This resolver gives the rest of the codebase ONE call site:
 *   $cfg = AiConfigResolver::for($workspace, 'email');
 * and returns a flat object with the merged configuration.
 *
 * Fallback waterfall for behavior values:
 *   1. ai_channel_configs row for this (workspace, channel)
 *   2. ai_configs (legacy global behavior on the same row)
 *   3. hard-coded sensible defaults
 *
 * Backward compatibility: workspaces that never visit the new settings
 * UI continue to work — we read from ai_configs as before.
 */
class AiConfigResolver
{
    /**
     * Resolve the effective AI config for a workspace + channel.
     *
     * @return ResolvedAiConfig
     */
    public static function for(Workspace $workspace, string $channel = 'email'): ResolvedAiConfig
    {
        $global = $workspace->aiConfig ?? AiConfig::where('workspace_id', $workspace->id)->first();
        $channelRow = AiChannelConfig::where('workspace_id', $workspace->id)
            ->where('channel', $channel)
            ->first();

        return new ResolvedAiConfig(
            workspace: $workspace,
            channel: $channel,
            globalConfig: $global,
            channelConfig: $channelRow,
        );
    }

    /**
     * Whether AI auto-reply is enabled for this workspace + channel.
     *
     * Logic:
     *   - If ai_channel_configs row exists → its `enabled` flag wins.
     *   - Otherwise fall back to ai_configs.auto_reply_enabled (legacy
     *     behavior — every channel uses the global flag).
     */
    public static function isEnabledFor(Workspace $workspace, string $channel): bool
    {
        return self::for($workspace, $channel)->enabled();
    }
}
