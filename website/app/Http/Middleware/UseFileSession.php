<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseFileSession
{
    /**
     * Force file-based sessions during installation.
     *
     * During the install wizard the database is not yet configured,
     * so the default "database" session driver would fail.  This
     * middleware temporarily switches to the file driver with a
     * fixed cookie name and disables encryption.
     */
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.driver'   => 'file',
            'session.cookie'   => 'mailtrixy_install_session',
            'session.encrypt'  => false,
        ]);

        return $next($request);
    }
}
