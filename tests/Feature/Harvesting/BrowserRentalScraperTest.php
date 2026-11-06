<?php

use App\Domains\Harvesting\Services\BrowserRentalScraper;
use App\Domains\Rentals\Models\Rental;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->tempPath = storage_path('framework/testing/scraper-'.uniqid());
    config([
        'harvesting.capture_path' => "{$this->tempPath}/captures",
        'harvesting.snapshot_path' => "{$this->tempPath}/snapshots",
        'sources.sites' => [
            'example' => [
                'name' => 'Example', 'listing_url' => 'https://example.com/alquileres',
                'link_base_url' => 'https://example.com/p/', 'wait_for' => '.item',
            ],
        ],
    ]);
    File::ensureDirectoryExists("{$this->tempPath}/captures");
    File::put("{$this->tempPath}/captures/example.html", '<li><img src="#img-0"><a href="/p/1">Depto</a></li>');
    File::put("{$this->tempPath}/captures/example.images.json", json_encode(['https://img.example.com/1.jpg']));

    Process::fake();
    Http::fake(['api.groq.com/*' => Http::response(['choices' => [[
        'message' => ['content' => json_encode(['rentals' => [[
            'Content' => 'Depto', 'Link' => 'https://example.com/p/1', 'Caracteristicas' => '', 'Price' => '$500.000',
            'Currency' => 'ARS', 'Location' => 'El Bolsón', 'Rooms' => 2, 'Bathrooms' => 1, 'Image' => '#img-0',
        ]]])],
        'finish_reason' => 'stop',
    ]]])]);
    app()->instance(\App\Domains\Harvesting\Services\RentalImageChecker::class, Mockery::mock(\App\Domains\Harvesting\Services\RentalImageChecker::class, ['isRented' => false]));
});

afterEach(fn () => File::deleteDirectory($this->tempPath));

it('passes the configured sites to the node scraper', function () {
    app(BrowserRentalScraper::class)->scrape();

    Process::assertRan(function ($process) {
        $input = json_decode($process->input, true);

        return $process->command === ['node', base_path('scripts/scrape.cjs')]
            && $input['capturePath'] === "{$this->tempPath}/captures"
            && $input['sites'] === [['key' => 'example', 'url' => 'https://example.com/alquileres', 'waitFor' => '.item']];
    });
});

it('stores new rentals with their image and skips unchanged captures', function () {
    $scraper = app(BrowserRentalScraper::class);

    expect($scraper->scrape())->toHaveCount(1);

    $rental = Rental::first();
    expect($rental->site_path)->toBe('https://example.com/alquileres')
        ->and($rental->image_url)->toBe('https://img.example.com/1.jpg')
        ->and(File::exists("{$this->tempPath}/snapshots/example.html"))->toBeTrue();

    // Same capture: the AI is not called again.
    $scraper->scrape();
    Http::assertSentCount(1);
});

it('does nothing when the node scraper fails', function () {
    Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);

    expect(app(BrowserRentalScraper::class)->scrape())->toBe([]);
    Http::assertNothingSent();
});
