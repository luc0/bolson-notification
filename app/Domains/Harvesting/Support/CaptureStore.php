<?php

namespace App\Domains\Harvesting\Support;

use App\Domains\Harvesting\Data\Capture;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\File;

/**
 * Captures written by scripts/scrape.cjs and snapshots of the last fully processed capture
 * of each source (see config/harvesting.php).
 */
class CaptureStore
{
    public function capturePath(): string
    {
        return config('harvesting.capture_path');
    }

    public function find(Source $source): ?Capture
    {
        $htmlPath = $this->capturePath()."/{$source->key}.html";
        if (! File::exists($htmlPath)) {
            return null;
        }

        $imagesPath = $this->capturePath()."/{$source->key}.images.json";
        $images = File::exists($imagesPath) ? json_decode(File::get($imagesPath), true) : [];

        return new Capture(File::get($htmlPath), $images ?? []);
    }

    public function isUnchanged(Source $source, Capture $capture): bool
    {
        $snapshotPath = $this->snapshotPath($source);

        return File::exists($snapshotPath) && File::get($snapshotPath) === $capture->snapshot();
    }

    /**
     * Only called once the capture was fully processed, so failed sources are retried next run.
     */
    public function markProcessed(Source $source, Capture $capture): void
    {
        File::ensureDirectoryExists(config('harvesting.snapshot_path'));
        File::put($this->snapshotPath($source), $capture->snapshot());
    }

    private function snapshotPath(Source $source): string
    {
        return config('harvesting.snapshot_path')."/{$source->key}.html";
    }
}
