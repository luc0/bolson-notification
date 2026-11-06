<?php

namespace App\Support;

final readonly class ParsedPrice
{
    public function __construct(
        public ?string $raw,
        public ?float $amount,
        public ?string $currency,
    ) {
    }
}
