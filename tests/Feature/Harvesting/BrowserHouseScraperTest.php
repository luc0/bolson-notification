<?php

use App\Domains\Harvesting\Services\BrowserHouseScraper;
use App\Domains\Houses\Models\House;
use App\Domains\Lands\Models\Land;
use App\Domains\Locations\Enums\City;
use App\Domains\Rentals\Models\Rental;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Http::fake() keeps the first stub registered, so the AI answers with whatever is set here at request time.
 */
function fakeHousesResponse(array $houses): void
{
    test()->housesResponse = $houses;
}

function houseItem(array $overrides = []): array
{
    return [
        'Content' => 'Casa en Mallín Ahogado', 'Link' => 'https://example.com/p/1', 'Caracteristicas' => "🔥 Salamandra\n🚗 Cochera",
        'Price' => 'U$S 135.000', 'Currency' => 'USD', 'Location' => 'Mallín Ahogado, El Bolsón',
        'Bedrooms' => 3, 'Bathrooms' => 2, 'CoveredArea' => 120, 'TotalArea' => 1200, 'Image' => '#img-0',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->tempPath = storage_path('framework/testing/house-scraper-'.uniqid());
    config([
        'harvesting.houses.capture_path' => "{$this->tempPath}/captures/houses",
        'harvesting.houses.snapshot_path' => "{$this->tempPath}/snapshots/houses",
        'sources.house_sites' => [
            'example' => [
                'name' => 'Example', 'listing_url' => 'https://example.com/casas',
                'link_base_url' => 'https://example.com/p/', 'wait_for' => null,
            ],
        ],
    ]);
    File::ensureDirectoryExists("{$this->tempPath}/captures/houses");
    File::put("{$this->tempPath}/captures/houses/example.html", '<li><img src="#img-0"><a href="/p/1">Casa</a></li>');
    File::put("{$this->tempPath}/captures/houses/example.images.json", json_encode(['https://img.example.com/1.jpg']));

    Process::fake();
    fakeHousesResponse([houseItem()]);
    Http::fake(['api.groq.com/*' => fn () => Http::response(['choices' => [[
        'message' => ['content' => json_encode(['houses' => $this->housesResponse])],
        'finish_reason' => 'stop',
    ]]])]);
});

afterEach(fn () => File::deleteDirectory($this->tempPath));

it('passes the house sites and their own capture folder to the node scraper', function () {
    app(BrowserHouseScraper::class)->scrape();

    Process::assertRan(function ($process) {
        $input = json_decode($process->input, true);

        return $input['capturePath'] === "{$this->tempPath}/captures/houses"
            && $input['sites'] === [['key' => 'example', 'url' => 'https://example.com/casas', 'waitFor' => null, 'nextPage' => null]];
    });
});

it('stores new houses with parsed price, specs and image, without touching rentals or lands', function () {
    expect(app(BrowserHouseScraper::class)->scrape())->toHaveCount(1);

    $house = House::first();
    expect($house->content)->toBe('Casa en Mallín Ahogado')
        ->and($house->price_currency)->toBe('USD')
        ->and((float) $house->price_amount)->toBe(135000.0)
        ->and($house->bedrooms)->toBe(3)
        ->and($house->bathrooms)->toBe(2)
        ->and($house->covered_area)->toBe(120.0)
        ->and($house->total_area)->toBe(1200.0)
        ->and($house->image_url)->toBe('https://img.example.com/1.jpg')
        ->and($house->site_path)->toBe('https://example.com/casas')
        ->and(Land::count())->toBe(0)
        ->and(Rental::count())->toBe(0)
        ->and(File::exists("{$this->tempPath}/snapshots/houses/example.html"))->toBeTrue();

    Http::assertSent(fn ($request) => $request['response_format']['json_schema']['name'] === 'houses');
});

it('resolves the city and neighborhood, falling back to the source default city', function () {
    config(['sources.house_sites.example.default_city' => 'el_bolson']);
    fakeHousesResponse([
        houseItem(),
        houseItem(['Link' => 'https://example.com/p/2', 'Content' => 'Casa', 'Location' => 'Lago Puelo']),
        houseItem(['Link' => 'https://example.com/p/3', 'Content' => 'Casa con vista', 'Location' => null]),
    ]);

    app(BrowserHouseScraper::class)->scrape();

    expect(House::where('source', 'https://example.com/p/1')->first())->city->toBe(City::ElBolson)->neighborhood->toBe('Mallín Ahogado')
        ->and(House::where('source', 'https://example.com/p/2')->value('city'))->toBe(City::LagoPuelo)
        ->and(House::where('source', 'https://example.com/p/3')->value('city'))->toBe(City::ElBolson);
});

it('classifies existing houses from the houses:classify command', function () {
    House::create(['source' => 'https://example.com/p/9', 'content' => 'Casa en Epuyén', 'site_path' => 'https://example.com/casas']);

    $this->artisan('houses:classify')->assertSuccessful();

    expect(House::first()->city)->toBe(City::Epuyen);
});

it('skips unchanged captures', function () {
    $scraper = app(BrowserHouseScraper::class);
    $scraper->scrape();
    $scraper->scrape();

    Http::assertSentCount(1);
});

it('deactivates houses that are no longer listed and reactivates them when they come back', function () {
    $scraper = app(BrowserHouseScraper::class);
    fakeHousesResponse([houseItem(), houseItem(['Link' => 'https://example.com/p/2'])]);
    $scraper->scrape();

    File::put("{$this->tempPath}/captures/houses/example.html", '<li>changed</li>');
    fakeHousesResponse([houseItem(['Link' => 'https://example.com/p/2'])]);
    expect($scraper->scrape())->toBe([]);
    expect(House::where('source', 'https://example.com/p/1')->value('active'))->toBeFalse();

    File::put("{$this->tempPath}/captures/houses/example.html", '<li>changed again</li>');
    fakeHousesResponse([houseItem(), houseItem(['Link' => 'https://example.com/p/2'])]);
    $scraper->scrape();
    expect(House::where('active', true)->count())->toBe(2);
});

it('stores zero specs as unknown', function () {
    fakeHousesResponse([houseItem(['Bedrooms' => 0, 'CoveredArea' => 0, 'TotalArea' => 0])]);

    app(BrowserHouseScraper::class)->scrape();

    expect(House::first())->bedrooms->toBeNull()->covered_area->toBeNull()->total_area->toBeNull();
});

it('treats placeholder sale prices as unknown', function () {
    fakeHousesResponse([houseItem(['Price' => 'U\$S 200'])]);

    app(BrowserHouseScraper::class)->scrape();

    expect(House::first())->price->toBe('U\$S 200')->price_amount->toBeNull()->price_currency->toBeNull();
});

it('does nothing when the node scraper fails', function () {
    Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);

    expect(app(BrowserHouseScraper::class)->scrape())->toBe([]);
    Http::assertNothingSent();
});

it('runs from the houses:analyze command', function () {
    $this->artisan('houses:analyze')->expectsOutput('1 new houses.')->assertSuccessful();

    expect(House::count())->toBe(1);
});
