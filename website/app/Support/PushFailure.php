<?php

namespace App\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/** Safe diagnostics shared by the device check and administrator CLI. */
final class PushFailure
{
    public static function code(\Throwable $error): string
    {
        $connection = false;
        for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof ConnectException || $cause instanceof GuzzleRequestException) {
                $errno = (int) ($cause->getHandlerContext()['errno'] ?? 0);
                $code = match ($errno) {
                    5, 6 => 'provider_dns_error',
                    7 => 'provider_connection_refused',
                    28 => 'provider_timeout',
                    35, 51, 58, 60, 77, 83, 90, 91 => 'provider_tls_error',
                    default => null,
                };
                if ($code !== null) return $code;
            }
            if ($cause instanceof RequestException) {
                return match ($cause->response->status()) {
                    400, 401, 403 => 'authentication_rejected',
                    429 => 'QUOTA_EXCEEDED',
                    default => 'provider_unavailable',
                };
            }
            $connection = $connection || $cause instanceof ConnectionException || $cause instanceof ConnectException;
        }
        return $connection ? 'provider_unreachable' : 'push_server_error';
    }

    public static function hint(string $code): string
    {
        return match ($code) {
            'provider_dns_error' => 'The hosting server cannot resolve Google hosts. Check server DNS for oauth2.googleapis.com and fcm.googleapis.com.',
            'provider_connection_refused' => 'The hosting server cannot connect to Google. Allow outbound HTTPS (port 443) to oauth2.googleapis.com and fcm.googleapis.com.',
            'provider_timeout' => 'The hosting server timed out connecting to Google. Check outbound HTTPS, proxy and IPv4/IPv6 routing.',
            'provider_tls_error' => 'The hosting server could not establish trusted TLS with Google. Update its CA certificates and PHP curl.cainfo/openssl.cafile settings; keep certificate verification enabled.',
            'authentication_rejected' => 'Google rejected the service-account login. Check the active Firebase key, service-account status and server clock.',
            'QUOTA_EXCEEDED' => 'Google rate-limited the request. Retry later and check the project quota.',
            'provider_unavailable' => 'Google returned an unexpected HTTP error. Check Google service availability and the hosting proxy.',
            'provider_unreachable' => 'The hosting server could not contact Google. Check DNS, outbound HTTPS, proxy and PHP TLS configuration.',
            default => 'A website error interrupted push delivery. Check the FCM operation failed log entry, Composer dependencies and server configuration.',
        };
    }
}
