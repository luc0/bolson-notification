<?php

use App\Domains\Sources\Data\Source;
use App\Domains\Sources\Enums\SourceType;

beforeEach(function () {
    config(['sources.sites' => [
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

it('reads all the sources from config', function () {
    $sources = Source::all();

    expect($sources)->toHaveCount(2)
        ->and($sources[0])->toEqual(new Source('agency', 'Agency', SourceType::Agency, 'https://agency.com/alquileres', 'https://agency.com/p/', '.item'))
        ->and($sources[1]->type)->toBe(SourceType::Portal)
        ->and($sources[1]->waitFor)->toBeNull();
});

it('finds a source by key', function () {
    expect(Source::find('portal')->name)->toBe('Portal');
});

it('fails for unknown sources', function () {
    Source::find('missing');
})->throws(InvalidArgumentException::class, 'Unknown source [missing].');

it('has a valid definition for every configured site', function () {
    // beforeEach replaced them with fixtures: load the real ones.
    config(['sources.sites' => (require config_path('sources.php'))['sites']]);

    expect(Source::all())->not->toBeEmpty()
        ->each(fn ($source) => $source->listingUrl->toStartWith('https://'));
});
