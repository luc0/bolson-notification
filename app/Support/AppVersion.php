<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Version of the running code: config('app.version') or the short hash of the deployed git commit.
 * Read from the .git folder (no git process per request).
 */
class AppVersion
{
    public const UNKNOWN = 'dev';

    public static function current(): string
    {
        return once(fn () => config('app.version') ?: self::fromGit(base_path('.git')) ?? self::UNKNOWN);
    }

    public static function fromGit(string $gitDir): ?string
    {
        $headFile = "{$gitDir}/HEAD";
        if (! File::exists($headFile)) {
            return null;
        }

        $head = trim(File::get($headFile));
        $isDetached = ! Str::startsWith($head, 'ref: ');
        if ($isDetached) {
            return self::short($head);
        }

        $ref = Str::after($head, 'ref: ');
        $refFile = "{$gitDir}/{$ref}";
        if (File::exists($refFile)) {
            return self::short(trim(File::get($refFile)));
        }

        return self::fromPackedRefs("{$gitDir}/packed-refs", $ref);
    }

    private static function fromPackedRefs(string $packedRefsFile, string $ref): ?string
    {
        if (! File::exists($packedRefsFile)) {
            return null;
        }

        $line = collect(File::lines($packedRefsFile))->first(fn (string $line) => Str::endsWith($line, " {$ref}"));

        return $line === null ? null : self::short(Str::before($line, ' '));
    }

    private static function short(string $hash): ?string
    {
        $isHash = preg_match('/^[0-9a-f]{7,40}$/', $hash) === 1;

        return $isHash ? substr($hash, 0, 7) : null;
    }
}
