<?php

namespace App\Support;

use App\Models\SystemSetting;

/** Apply the admin integration toggle to the transport used by web and mobile. */
class RealtimeSettings
{
    public static function apply(): void
    {
        $enabled = SystemSetting::get('integration_pusher_enabled', '');
        if ($enabled === '') return; // No admin override: keep the environment configuration.
        if ($enabled !== '1') {
            config(['broadcasting.default' => 'null']);
            return;
        }
        $values = [];
        foreach (['app_id', 'key', 'secret'] as $field) {
            $value = config('services.pusher.'.$field);
            if (!$value) { config(['broadcasting.default' => 'null']); return; }
            $values['broadcasting.connections.pusher.'.$field] = $value;
        }
        $cluster = config('services.pusher.cluster') ?: 'mt1';
        $values['broadcasting.default'] = 'pusher';
        $values['broadcasting.connections.pusher.options.cluster'] = $cluster;
        $host = config('broadcasting.connections.pusher.options.host');
        if (!$host || preg_match('/^api-[a-z0-9-]+\.pusher\.com$/i', $host)) {
            $values['broadcasting.connections.pusher.options.host'] = 'api-'.$cluster.'.pusher.com';
        }
        config($values);
    }
}
