<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ChannelIntegration extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'channel',
        'status',
        'credentials',
        'config',
        'ai_auto_reply',
        'error_message',
        'account_name',
        'refresh_token',
        'token_expires_at',
        'zapier_webhook_token',
        'zapier_api_token',
        'salesforce_instance_url',
        'slack_team_id',
        'slack_bot_token',
        'slack_channel_id',
    ];

    protected $hidden = [
        'slack_bot_token',
        'zapier_webhook_token',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'ai_auto_reply' => 'boolean',
            'token_expires_at' => 'datetime',
            'refresh_token' => 'encrypted',
            'zapier_api_token' => 'encrypted',
            'slack_bot_token' => 'encrypted',
            'zapier_webhook_token' => 'encrypted',
        ];
    }

    /**
     * Encrypt each credential value individually before storing as JSON.
     * This keeps the column as valid JSON (MySQL json type) while protecting secrets at rest.
     */
    public function setCredentialsAttribute($value): void
    {
        if (is_array($value)) {
            $encrypted = [];
            foreach ($value as $k => $v) {
                $encrypted[$k] = $v ? Crypt::encryptString((string) $v) : null;
            }
            $this->attributes['credentials'] = json_encode($encrypted);
        } else {
            $this->attributes['credentials'] = $value;
        }
    }

    /**
     * Decrypt each credential value when reading. Falls back gracefully for legacy unencrypted data.
     */
    public function getCredentialsAttribute($value): array
    {
        if (!$value) {
            return [];
        }

        $data = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($data)) {
            return [];
        }

        $decrypted = [];
        foreach ($data as $k => $v) {
            try {
                $decrypted[$k] = $v ? Crypt::decryptString($v) : null;
            } catch (\Exception $e) {
                // Value might not be encrypted (legacy data) — pass through
                $decrypted[$k] = $v;
            }
        }

        return $decrypted;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
