<?php

namespace App\Http\Middleware;

use App\Domains\Members\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stores when the member last opened the app (users.last_seen_at), at most once per hour
 * to avoid a write on every request. Used to know who still uses the app.
 */
class TrackLastSeen
{
    private const UPDATE_EVERY_MINUTES = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isStale = $user instanceof User && ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinutes(self::UPDATE_EVERY_MINUTES)));
        if ($isStale) {
            User::withoutTimestamps(fn () => $user->forceFill(['last_seen_at' => now()])->saveQuietly());
        }

        return $next($request);
    }
}
