<?php

use App\Domains\Sources\Data\Source;
use App\Domains\Sources\Enums\SourceType;

beforeEach(function () {
    config(['sources.rental_sites' => [
        'agency' => [
            'name' => 'Agency', 'type' => 'agency', 'listing_url' => 'https://agency.com/alquileres',
            'link_base_url' => 'https://agency.com/p/', 'wait_for' => '.item',
        ],
        'portal' => [
            'name' => 'Portal', 'type' => 'portal', 'listing_url' => 'https://portal.com/s/el-bolson',
            'link_base_url' => 'https://portal.com',
        ],
    ]]);
});

it('reads the rental sources from config', function () {
    $sources = Source::rentals();

    expect($sources)->toHaveCount(2)
        ->and($sources[0])->toEqual(new Source('agency', 'Agency', SourceType::Agency, 'https://agency.com/alquileres', 'https://agency.com/p/', '.item'))
        ->and($sources[1]->type)->toBe(SourceType::Portal)
        ->and($sources[1]->waitFor)->toBeNull();
});

it('finds a rental source by key', function () {
    expect(Source::findRental('portal')->name)->toBe('Portal');
});

it('fails for unknown sources', function () {
    Source::findRental('missing');
})->throws(InvalidArgumentException::class, 'Unknown rental source [missing].');

it('has a valid definition for every configured rental site', function () {
    // beforeEach replaced them with fixtures: load the real ones.
    config(['sources.rental_sites' => (require config_path('sources.php'))['rental_sites']]);

    expect(Source::rentals())->not->toBeEmpty()
        ->each(fn ($source) => $source->listingUrl->toStartWith('https://'));
});

it('reads the land sources apart from the rental ones', function () {
    config(['sources.land_sites' => [
        'agency' => ['name' => 'Agency', 'type' => 'agency', 'listing_url' => 'https://agency.com/terrenos', 'link_base_url' => 'https://agency.com/p/'],
    ]]);

    expect(Source::lands())->toHaveCount(1)
        ->and(Source::lands()[0]->listingUrl)->toBe('https://agency.com/terrenos')
        ->and(Source::rentals())->toHaveCount(2);
});

it('has a valid definition for every configured land site', function () {
    config(['sources.land_sites' => (require config_path('sources.php'))['land_sites']]);

    expect(Source::lands())->not->toBeEmpty()
        ->each(fn ($source) => $source->listingUrl->toStartWith('https://'));
});
