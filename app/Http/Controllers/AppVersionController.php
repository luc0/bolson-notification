<?php

namespace App\Http\Controllers;

use App\Support\AppVersion;
use Illuminate\Http\JsonResponse;

/**
 * Polled by the installed app when it comes back to the foreground: a different version means a deploy happened.
 */
class AppVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['version' => AppVersion::current()])
            ->header('Cache-Control', 'no-store');
    }
}
