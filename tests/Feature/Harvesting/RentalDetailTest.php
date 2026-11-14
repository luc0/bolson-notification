<?php

use App\Domains\Harvesting\Services\RentalDetailExtractor;
use App\Domains\Harvesting\Services\RentalDetailFetcher;
use App\Domains\Harvesting\Services\RentalDetailService;
use App\Domains\Rentals\Models\Rental;
use Illuminate\Support\Facades\Http;

const DETAIL_PAGE_TEXT = "Superficie cubierta 45 m²\nAmbientes : 2\nDormitorios : 1\nBaños : 1\nCocheras : 1\nAntigüedad : 6 Años\nSERVICIOS\nAgua Corriente\nCloaca\nGas Natural";

function fakeDetailAnswer(array $detail): void
{
    Http::fake([
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode($detail)]]]]),
    ]);
}

function detailAnswer(array $overrides = []): array
{
    return [
        'Title' => 'Departamento de 1 dormitorio con cochera', 'CoveredArea' => 45, 'TotalArea' => null, 'Rooms' => 2, 'Bedrooms' => 1, 'Bathrooms' => 1,
        'ParkingSpaces' => 1, 'AgeYears' => 6, 'City' => null, 'Neighborhood' => null, 'PropertyUse' => null,
        'Services' => ['running_water', 'sewer', 'natural_gas'],
        ...$overrides,
    ];
}

function rentalForDetail(array $attributes = []): Rental
{
    return Rental::create([
        'source' => 'https://example.com/p/1-Bolsón', 'content' => 'Depto', 'description' => '', 'active' => true,
        ...$attributes,
    ]);
}

it('extracts the detail with a strict schema', function () {
    fakeDetailAnswer(detailAnswer());

    $detail = app(RentalDetailExtractor::class)->extract(DETAIL_PAGE_TEXT, 'https://example.com/p/1');

    expect($detail)->toBe(detailAnswer());
    Http::assertSent(fn ($request) => $request['response_format']['json_schema']['name'] === 'rental_detail');
});

it('drops values without evidence on the page', function () {
    fakeDetailAnswer(detailAnswer(['AgeYears' => 2, 'ParkingSpaces' => 1]));

    // e.g. "Alquiler por 2 años" taken as the building age.
    $detail = app(RentalDetailExtractor::class)->extract("Dormitorios: 1\nBaños: 1\nAlquiler por 2 años", 'https://example.com/p/1');

    expect($detail['AgeYears'])->toBeNull()
        ->and($detail['ParkingSpaces'])->toBeNull()
        ->and($detail['Bedrooms'])->toBe(1);
});

it('fetches the page as text and encodes non ascii urls', function () {
    Http::fake(['example.com/*' => Http::response('<html><script>x()</script><body><h1>Depto</h1><p>Baños: 1</p>&lt;p&gt;Gas natural&lt;/p&gt;</body></html>')]);

    $text = app(RentalDetailFetcher::class)->fetchText('https://example.com/p/1-Bolsón,-centro');

    expect($text)->toBe("Depto\nBaños: 1\nGas natural");
    Http::assertSent(fn ($request) => $request->url() === 'https://example.com/p/1-Bols%C3%B3n,-centro');
});

it('stores the detail and prefers its values over the listing card', function () {
    fakeDetailAnswer(detailAnswer());
    $fetcher = Mockery::mock(RentalDetailFetcher::class);
    $fetcher->shouldReceive('fetchText')->andReturn(DETAIL_PAGE_TEXT);
    app()->instance(RentalDetailFetcher::class, $fetcher);
    $rental = rentalForDetail(['rooms' => 3, 'bathrooms' => 0]);

    expect(app(RentalDetailService::class)->enrichPending())->toBe(1);

    $rental->refresh();
    expect($rental->covered_area)->toBe(45.0)
        ->and($rental->rooms)->toBe(2)
        ->and($rental->bedrooms)->toBe(1)
        ->and($rental->bathrooms)->toBe(1)
        ->and($rental->parking_spaces)->toBe(1)
        ->and($rental->age_years)->toBe(6)
        ->and($rental->title)->toBe('Departamento de 1 dormitorio con cochera')
        ->and($rental->services)->toBe(['running_water', 'sewer', 'natural_gas'])
        ->and($rental->details_fetched_at)->not->toBeNull();
});

it('retries failed rentals up to the max attempts', function () {
    $fetcher = Mockery::mock(RentalDetailFetcher::class);
    $fetcher->shouldReceive('fetchText')->times(RentalDetailService::MAX_ATTEMPTS)->andReturn(null);
    app()->instance(RentalDetailFetcher::class, $fetcher);
    $rental = rentalForDetail();

    foreach (range(1, RentalDetailService::MAX_ATTEMPTS + 2) as $run) {
        app(RentalDetailService::class)->enrichPending();
    }

    expect($rental->refresh()->details_attempts)->toBe(RentalDetailService::MAX_ATTEMPTS)
        ->and($rental->details_fetched_at)->toBeNull();
});

it('builds friendly specs and service labels', function () {
    $rental = new Rental([
        'covered_area' => 45, 'total_area' => 1200, 'rooms' => 2, 'bedrooms' => 1, 'bathrooms' => 1,
        'parking_spaces' => 2, 'age_years' => 0, 'services' => ['sewer', 'electricity', 'natural_gas'],
    ]);

    expect($rental->specs())->toBe([
        '📐 45 m² cubiertos · 1.200 m² de terreno',
        '🛏️ 2 ambientes · 1 dormitorio',
        '🚿 1 baño',
        '🚗 2 cocheras',
        '🏗️ A estrenar',
    ])->and($rental->serviceLabels())->toBe(['Gas natural', 'Luz', 'Cloacas']);
});

it('omits specs without data', function () {
    $rental = new Rental(['rooms' => 0, 'bathrooms' => null, 'services' => []]);

    expect($rental->specs())->toBe([])->and($rental->serviceLabels())->toBe([]);
});

it('accepts city and neighborhood only when they are on the page', function () {
    fakeDetailAnswer(detailAnswer(['City' => 'lago_puelo', 'Neighborhood' => 'Las Golondrinas']));
    $detail = app(RentalDetailExtractor::class)->extract("Ubicación: Lago Puelo\nBarrio: Las Golondrinas", 'https://example.com/p/1');
    expect($detail['City'])->toBe('lago_puelo')->and($detail['Neighborhood'])->toBe('Las Golondrinas');

    fakeDetailAnswer(detailAnswer(['City' => 'cholila', 'Neighborhood' => 'Villa Turismo']));
    $detail = app(RentalDetailExtractor::class)->extract('Ubicación: El Bolsón', 'https://example.com/p/1');
    expect($detail['City'])->toBeNull()->and($detail['Neighborhood'])->toBeNull();
});

it('uses the detail city only when the rental has none', function (?string $currentCity, string $expectedCity) {
    fakeDetailAnswer(detailAnswer(['City' => 'lago_puelo']));
    $fetcher = Mockery::mock(RentalDetailFetcher::class);
    $fetcher->shouldReceive('fetchText')->andReturn(DETAIL_PAGE_TEXT."\nUbicación: Lago Puelo");
    app()->instance(RentalDetailFetcher::class, $fetcher);
    $rental = rentalForDetail(['city' => $currentCity]);

    app(RentalDetailService::class)->enrich($rental);

    expect($rental->refresh()->city->value)->toBe($expectedCity);
})->with([
    'without city' => [null, 'lago_puelo'],
    'with city from the listing' => ['el_bolson', 'el_bolson'],
]);

it('cleans the ai title and drops it when it has a price or is too long', function (?string $aiTitle, ?string $expected) {
    fakeDetailAnswer(detailAnswer(['Title' => $aiTitle]));

    $detail = app(RentalDetailExtractor::class)->extract(DETAIL_PAGE_TEXT, 'https://example.com/p/1');

    expect($detail['Title'])->toBe($expected);
})->with([
    'trimmed and capitalized' => ['  casa con parque en  Mallín Ahogado. ', 'Casa con parque en Mallín Ahogado'],
    'with price' => ['Casa de 2 dormitorios a $500.000', null],
    'too long' => [str_repeat('casa linda ', 12), null],
    'in capitals' => ['DEPARTAMENTO DE 2 DORMITORIOS EN LOS ALAMOS', null],
    'empty' => ['', null],
]);

it('backfills only rentals without an ai title', function () {
    fakeDetailAnswer(detailAnswer());
    $fetcher = Mockery::mock(RentalDetailFetcher::class);
    $fetcher->shouldReceive('fetchText')->once()->andReturn(DETAIL_PAGE_TEXT);
    app()->instance(RentalDetailFetcher::class, $fetcher);
    $withoutTitle = rentalForDetail(['details_fetched_at' => now()]);
    rentalForDetail(['source' => 'https://example.com/p/2', 'details_fetched_at' => now(), 'title' => 'Casa']);

    expect(app(RentalDetailService::class)->enrichPending(missingTitlesOnly: true))->toBe(1)
        ->and($withoutTitle->refresh()->title)->toBe('Departamento de 1 dormitorio con cochera');
});

it('shows the ai title on the card and keeps the agency title as tooltip', function () {
    rentalForDetail(['property_use' => 'residential', 'content' => 'Departamento en Alquiler en El Bolson, Rio Negro', 'title' => 'Departamento de 1 dormitorio con cochera']);

    $this->actingAs(subscribedMember())->get(route('rentals.index'))
        ->assertSee('Departamento de 1 dormitorio con cochera')
        ->assertSee('title="Departamento en Alquiler en El Bolson, Rio Negro"', false);
});
