<?php

namespace App\Contracts;

interface RentalScraper
{
    public function scrape(): array;
}
