<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = env('INSTALLED', '0') === '1' || file_exists(storage_path('installed'));
        $isInstallRoute = str_starts_with(trim($request->getPathInfo(), '/'), 'install');

        // When app is not installed, force file session with a fixed cookie name.
        // The cookie name must NOT depend on APP_NAME because writeEnvFile()
        // changes APP_NAME mid-installation, which would change the cookie name
        // and cause all subsequent AJAX requests to get a new empty session.
        if (! $installed) {
            config([
                'session.driver'  => 'file',
                'session.encrypt' => false,
                'session.cookie'  => 'mailtrixy_install_session',
                'cache.default'   => 'file',
                'queue.default'   => 'sync',
            ]);

            // Force cache manager to use new file driver
            app('cache')->forgetDriver('database');
        }

        if (! $installed && ! $isInstallRoute) {
            return redirect(url('/install'));
        }

        // Skip all DB-dependent middleware during installation
        if (! $installed && $isInstallRoute) {
            return $next($request);
        }

        if ($installed && $isInstallRoute) {
            return redirect(url('/'));
        }

        return $next($request);
    }
}
