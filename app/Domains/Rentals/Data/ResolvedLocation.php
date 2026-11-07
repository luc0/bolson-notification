<?php

namespace App\Domains\Rentals\Data;

use App\Domains\Rentals\Enums\City;

final readonly class ResolvedLocation
{
    public function __construct(
        public ?City $city = null,
        public ?string $neighborhood = null,
    ) {}
}
