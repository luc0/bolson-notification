<?php

use App\Domains\Harvesting\Services\BrowserLandScraper;
use App\Domains\Lands\Models\Land;
use App\Domains\Locations\Enums\City;
use App\Domains\Rentals\Models\Rental;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Http::fake() keeps the first stub registered, so the AI answers with whatever is set here at request time.
 */
function fakeLandsResponse(array $lands): void
{
    test()->landsResponse = $lands;
}

function landItem(array $overrides = []): array
{
    return [
        'Content' => 'Lote en Mallín Ahogado', 'Link' => 'https://example.com/p/1', 'Caracteristicas' => "💡 Luz\n🚗 Acceso todo el año",
        'Price' => 'U$S 35.000', 'Currency' => 'USD', 'Location' => 'Mallín Ahogado, El Bolsón', 'Area' => 1200, 'Image' => '#img-0',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->tempPath = storage_path('framework/testing/land-scraper-'.uniqid());
    config([
        'harvesting.lands.capture_path' => "{$this->tempPath}/captures/lands",
        'harvesting.lands.snapshot_path' => "{$this->tempPath}/snapshots/lands",
        'sources.land_sites' => [
            'example' => [
                'name' => 'Example', 'listing_url' => 'https://example.com/terrenos',
                'link_base_url' => 'https://example.com/p/', 'wait_for' => null,
            ],
        ],
    ]);
    File::ensureDirectoryExists("{$this->tempPath}/captures/lands");
    File::put("{$this->tempPath}/captures/lands/example.html", '<li><img src="#img-0"><a href="/p/1">Lote</a></li>');
    File::put("{$this->tempPath}/captures/lands/example.images.json", json_encode(['https://img.example.com/1.jpg']));

    Process::fake();
    fakeLandsResponse([landItem()]);
    Http::fake(['api.groq.com/*' => fn () => Http::response(['choices' => [[
        'message' => ['content' => json_encode(['lands' => $this->landsResponse])],
        'finish_reason' => 'stop',
    ]]])]);
});

afterEach(fn () => File::deleteDirectory($this->tempPath));

it('passes the land sites and their own capture folder to the node scraper', function () {
    app(BrowserLandScraper::class)->scrape();

    Process::assertRan(function ($process) {
        $input = json_decode($process->input, true);

        return $input['capturePath'] === "{$this->tempPath}/captures/lands"
            && $input['sites'] === [['key' => 'example', 'url' => 'https://example.com/terrenos', 'waitFor' => null]];
    });
});

it('stores new lands with parsed price, area and image, without touching rentals', function () {
    expect(app(BrowserLandScraper::class)->scrape())->toHaveCount(1);

    $land = Land::first();
    expect($land->content)->toBe('Lote en Mallín Ahogado')
        ->and($land->price_currency)->toBe('USD')
        ->and((float) $land->price_amount)->toBe(35000.0)
        ->and($land->area)->toBe(1200.0)
        ->and($land->image_url)->toBe('https://img.example.com/1.jpg')
        ->and($land->site_path)->toBe('https://example.com/terrenos')
        ->and(Rental::count())->toBe(0)
        ->and(File::exists("{$this->tempPath}/snapshots/lands/example.html"))->toBeTrue();

    Http::assertSent(fn ($request) => $request['response_format']['json_schema']['name'] === 'lands');
});

it('resolves the city and neighborhood, falling back to the source default city', function () {
    config(['sources.land_sites.example.default_city' => 'el_bolson']);
    fakeLandsResponse([
        landItem(),
        landItem(['Link' => 'https://example.com/p/2', 'Content' => 'Lote', 'Location' => 'Lago Puelo']),
        landItem(['Link' => 'https://example.com/p/3', 'Content' => 'Lote con vista', 'Location' => null]),
    ]);

    app(BrowserLandScraper::class)->scrape();

    expect(Land::where('source', 'https://example.com/p/1')->first())->city->toBe(City::ElBolson)->neighborhood->toBe('Mallín Ahogado')
        ->and(Land::where('source', 'https://example.com/p/2')->value('city'))->toBe(City::LagoPuelo)
        ->and(Land::where('source', 'https://example.com/p/3')->value('city'))->toBe(City::ElBolson);
});

it('classifies existing lands from the lands:classify command', function () {
    Land::create(['source' => 'https://example.com/p/9', 'content' => 'Lote en Epuyén', 'site_path' => 'https://example.com/terrenos']);

    $this->artisan('lands:classify')->assertSuccessful();

    expect(Land::first()->city)->toBe(City::Epuyen);
});

it('skips unchanged captures', function () {
    $scraper = app(BrowserLandScraper::class);
    $scraper->scrape();
    $scraper->scrape();

    Http::assertSentCount(1);
});

it('deactivates lands that are no longer listed and reactivates them when they come back', function () {
    $scraper = app(BrowserLandScraper::class);
    fakeLandsResponse([landItem(), landItem(['Link' => 'https://example.com/p/2'])]);
    $scraper->scrape();

    File::put("{$this->tempPath}/captures/lands/example.html", '<li>changed</li>');
    fakeLandsResponse([landItem(['Link' => 'https://example.com/p/2'])]);
    expect($scraper->scrape())->toBe([]);
    expect(Land::where('source', 'https://example.com/p/1')->value('active'))->toBeFalse();

    File::put("{$this->tempPath}/captures/lands/example.html", '<li>changed again</li>');
    fakeLandsResponse([landItem(), landItem(['Link' => 'https://example.com/p/2'])]);
    $scraper->scrape();
    expect(Land::where('active', true)->count())->toBe(2);
});

it('stores a zero area as unknown', function () {
    fakeLandsResponse([landItem(['Area' => 0])]);

    app(BrowserLandScraper::class)->scrape();

    expect(Land::first()->area)->toBeNull();
});

it('treats placeholder sale prices as unknown', function () {
    fakeLandsResponse([landItem(['Price' => 'U\$S 200'])]);

    app(BrowserLandScraper::class)->scrape();

    expect(Land::first())->price->toBe('U\$S 200')->price_amount->toBeNull()->price_currency->toBeNull();
});

it('does nothing when the node scraper fails', function () {
    Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);

    expect(app(BrowserLandScraper::class)->scrape())->toBe([]);
    Http::assertNothingSent();
});

it('runs from the lands:analyze command', function () {
    $this->artisan('lands:analyze')->expectsOutput('1 new lands.')->assertSuccessful();

    expect(Land::count())->toBe(1);
});
