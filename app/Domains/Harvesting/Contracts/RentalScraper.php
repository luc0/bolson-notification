<?php

namespace App\Domains\Harvesting\Contracts;

interface RentalScraper
{
    public function scrape(): array;
}
