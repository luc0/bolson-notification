<?php

namespace App\Domains\Harvesting\Data;

final readonly class ImportResult
{
    /**
     * @param  array<int, array>  $newItems  Items that were not in the DB before this run.
     * @param  int  $pendingPriceCount  Available listings of the source with a new price waiting for a second read.
     */
    public function __construct(
        public array $newItems,
        public int $failedCount,
        public int $pendingPriceCount = 0,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failedCount > 0;
    }

    public function hasPendingPrices(): bool
    {
        return $this->pendingPriceCount > 0;
    }
}
