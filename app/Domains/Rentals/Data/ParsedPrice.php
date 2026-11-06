<?php

namespace App\Domains\Rentals\Data;

final readonly class ParsedPrice
{
    public function __construct(
        public ?string $raw,
        public ?float $amount,
        public ?string $currency,
    ) {
    }
}
