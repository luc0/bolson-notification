<?php

use App\Domains\Harvesting\Services\RentalImporter;
use App\Domains\Rentals\Models\Rental;

const SITE_URL = 'https://example.com/alquileres';

function scrapedItem(string $link, mixed $price = '$650.000 ARS'): array
{
    return [
        'Content' => "Rental {$link}",
        'Link' => $link,
        'Caracteristicas' => 'ubicación: El Bolsón',
        'Price' => $price,
        'Location' => 'El Bolsón',
        'Rooms' => 2,
        'Bathrooms' => 1,
    ];
}

it('stores "Consultar" prices without failing', function () {
    $result = app(RentalImporter::class)->import([scrapedItem('https://example.com/1', 'Consultar')], SITE_URL);

    expect($result->hasFailures())->toBeFalse()
        ->and($result->newItems)->toHaveCount(1);

    $rental = Rental::first();
    expect($rental->price)->toBe('Consultar')
        ->and($rental->price_amount)->toBeNull();
});

it('keeps storing the rest of the items when one fails', function () {
    $brokenItem = ['Content' => 'no link'];

    $result = app(RentalImporter::class)->import([
        scrapedItem('https://example.com/1'),
        $brokenItem,
        scrapedItem('https://example.com/2'),
    ], SITE_URL);

    expect($result->failedCount)->toBe(1)
        ->and($result->newItems)->toHaveCount(2)
        ->and(Rental::count())->toBe(2);
});

it('only returns new rentals and deactivates missing ones', function () {
    $service = app(RentalImporter::class);
    $service->import([scrapedItem('https://example.com/1'), scrapedItem('https://example.com/2')], SITE_URL);

    $result = $service->import([scrapedItem('https://example.com/1'), scrapedItem('https://example.com/3')], SITE_URL);

    expect(collect($result->newItems)->pluck('Link')->all())->toBe(['https://example.com/3'])
        ->and(Rental::where('source', 'https://example.com/2')->first()->active)->toBeFalse()
        ->and(Rental::where('source', 'https://example.com/1')->first()->active)->toBeTrue();
});

function fakeImageChecker(?bool $answer): object
{
    $checker = new class($answer) extends \App\Domains\Harvesting\Services\RentalImageChecker
    {
        public array $checkedUrls = [];

        public function __construct(private ?bool $answer) {}

        public function isRented(string $imageUrl): ?bool
        {
            $this->checkedUrls[] = $imageUrl;

            return $this->answer;
        }
    };
    app()->instance(\App\Domains\Harvesting\Services\RentalImageChecker::class, $checker);

    return $checker;
}

it('marks rentals with a rented photo and does not notify them as new', function () {
    fakeImageChecker(true);

    $result = app(RentalImporter::class)->import([[...scrapedItem('https://example.com/1'), 'Image' => 'https://img/1.jpg']], SITE_URL);

    $rental = Rental::first();
    expect($result->newItems)->toBeEmpty()
        ->and($rental->isRented())->toBeTrue()
        ->and($rental->image_checked_url)->toBe('https://img/1.jpg');
});

it('checks each image only once and rechecks when the image changes', function () {
    $checker = fakeImageChecker(false);
    $service = app(RentalImporter::class);
    $item = [...scrapedItem('https://example.com/1'), 'Image' => 'https://img/1.jpg'];

    $service->import([$item], SITE_URL);
    $service->import([$item], SITE_URL);
    $service->import([[...$item, 'Image' => 'https://img/1-stamped.jpg']], SITE_URL);

    expect($checker->checkedUrls)->toBe(['https://img/1.jpg', 'https://img/1-stamped.jpg']);
});

it('keeps the rental available and retries later when the image check fails', function () {
    fakeImageChecker(null);

    $result = app(RentalImporter::class)->import([[...scrapedItem('https://example.com/1'), 'Image' => 'https://img/1.jpg']], SITE_URL);

    $rental = Rental::first();
    expect($result->newItems)->toHaveCount(1)
        ->and($result->hasFailures())->toBeFalse()
        ->and($rental->isRented())->toBeFalse()
        ->and($rental->image_checked_url)->toBeNull();
});

it('treats encoded and decoded links as the same rental', function () {
    $service = app(RentalImporter::class);
    $service->import([scrapedItem('https://example.com/p/1-Caba%C3%B1a')], SITE_URL);

    $result = $service->import([scrapedItem('https://example.com/p/1-Cabaña')], SITE_URL);

    expect($result->newItems)->toBeEmpty()
        ->and(Rental::count())->toBe(1)
        ->and(Rental::first()->source)->toBe('https://example.com/p/1-Cabaña')
        ->and(Rental::first()->active)->toBeTrue();
});

it('uses the currency returned by the AI', function () {
    app(RentalImporter::class)->import([[...scrapedItem('https://example.com/1', '4500'), 'Currency' => 'USD']], SITE_URL);

    expect(Rental::first()->price_currency)->toBe('USD')
        ->and(Rental::first()->formattedPrice())->toBe('U$S 4.500');
});
