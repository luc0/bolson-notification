<?php

namespace App\Domains\Harvesting\Data;

use App\Domains\Harvesting\Enums\MatchMethod;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored listing (rental, land or house) recognized in a new scrape, and how it was recognized.
 */
final readonly class ListingMatch
{
    public function __construct(
        public Model $listing,
        public MatchMethod $method,
    ) {}
}
