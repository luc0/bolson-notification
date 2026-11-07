<?php

namespace App\Domains\Harvesting\Support;

use App\Domains\Harvesting\Data\Capture;
use App\Domains\Sources\Data\Source;
use Illuminate\Support\Facades\File;

/**
 * Captures written by scripts/scrape.cjs and snapshots of the last fully processed capture
 * of each source (see config/harvesting.php). Each catalog has its own folders (`forRentals()`, `forLands()`),
 * so an agency key can appear in both.
 */
class CaptureStore
{
    public function __construct(
        private readonly string $capturePath,
        private readonly string $snapshotPath,
    ) {}

    public static function forRentals(): self
    {
        return new self(config('harvesting.rentals.capture_path'), config('harvesting.rentals.snapshot_path'));
    }

    public static function forLands(): self
    {
        return new self(config('harvesting.lands.capture_path'), config('harvesting.lands.snapshot_path'));
    }

    public function capturePath(): string
    {
        return $this->capturePath;
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
        $snapshotFile = $this->snapshotFile($source);

        return File::exists($snapshotFile) && File::get($snapshotFile) === $capture->snapshot();
    }

    /**
     * Only called once the capture was fully processed, so failed sources are retried next run.
     */
    public function markProcessed(Source $source, Capture $capture): void
    {
        File::ensureDirectoryExists($this->snapshotPath);
        File::put($this->snapshotFile($source), $capture->snapshot());
    }

    private function snapshotFile(Source $source): string
    {
        return $this->snapshotPath."/{$source->key}.html";
    }
}
