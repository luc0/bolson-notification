<?php

namespace App\Domains\Harvesting\Rentals\Contracts;

interface RentalScraper
{
    public function scrape(): array;
}
