<?php

namespace App\Support;

final readonly class RentalStoreResult
{
    /**
     * @param array<int, array> $newItems Items that were not in the DB before this run.
     */
    public function __construct(
        public array $newItems,
        public int $failedCount,
    ) {
    }

    public function hasFailures(): bool
    {
        return $this->failedCount > 0;
    }
}
