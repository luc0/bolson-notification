<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP basic auth (phone + password) for /logs, only for the phones in config('app.log_viewer_phones').
 */
class LogViewerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $failedAuthentication = auth()->onceBasic('phone');
        if ($failedAuthentication) {
            return $failedAuthentication;
        }

        $isAllowed = in_array($request->user()->phone, config('app.log_viewer_phones'), true);
        abort_unless($isAllowed, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
