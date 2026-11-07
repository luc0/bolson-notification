<?php

namespace App\Domains\Locations\Data;

use App\Domains\Locations\Enums\City;

final readonly class ResolvedLocation
{
    public function __construct(
        public ?City $city = null,
        public ?string $neighborhood = null,
    ) {}
}
