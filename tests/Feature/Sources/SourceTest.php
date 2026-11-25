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

it('reads the next page selector of paginated sources', function () {
    config(['sources.rental_sites.agency.next_page_selector' => 'a.next']);

    expect(Source::findRental('agency')->nextPageSelector)->toBe('a.next')
        ->and(Source::findRental('portal')->nextPageSelector)->toBeNull();
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

it('reads the house sources apart from the rental and land ones', function () {
    config(['sources.house_sites' => [
        'agency' => ['name' => 'Agency', 'type' => 'agency', 'listing_url' => 'https://agency.com/casas', 'link_base_url' => 'https://agency.com/p/'],
    ]]);

    expect(Source::houses())->toHaveCount(1)
        ->and(Source::findHouseByListingUrl('https://agency.com/casas')->key)->toBe('agency')
        ->and(Source::rentals())->toHaveCount(2);
});

it('has a valid definition for every configured house site', function () {
    config(['sources.house_sites' => (require config_path('sources.php'))['house_sites']]);

    expect(Source::houses())->not->toBeEmpty()
        ->each(fn ($source) => $source->listingUrl->toStartWith('https://'));
});

it('reads the agency id of a listing from its link', function () {
    config(['sources.rental_sites.agency.listing_id_pattern' => '#/p/(\d+)-#']);

    expect(Source::findRental('agency')->listingIdFrom('https://agency.com/p/6990365-Departamento-en-Alquiler'))->toBe('6990365')
        ->and(Source::findRental('agency')->listingIdFrom('https://agency.com/otra-cosa'))->toBeNull()
        ->and(Source::findRental('portal')->listingIdFrom('https://portal.com/p/1-casa'))->toBeNull();
});

it('reads the ids of the configured sites', function (string $key, string $link, string $id) {
    config(['sources' => require base_path('config/sources.php')]);
    $source = collect([...Source::rentals(), ...Source::lands(), ...Source::houses()])->first(fn (Source $source) => $source->key === $key);

    expect($source->listingIdFrom($link))->toBe($id);
})->with([
    ['rioazulpropiedades', 'https://www.rioazulpropiedades.com/p/6667459-Departamento-en-Alquiler-en-Centro-rivadavia', '6667459'],
    ['puntosurpropiedades', 'https://puntosurpropiedades.ar/web/propiedad.php?id_propiedad=655', '655'],
    ['inmobiliariadelagua', 'https://inmobiliariadelagua.com.ar/departamento-alquiler-casco-centrico-el-bolson/8587691', '8587691'],
    ['surpatagonian', 'https://surpatagonian.com/propiedad.html?id=b0d9b5b5-a0ab-4c34-b5da-be4186ea2f69', 'b0d9b5b5-a0ab-4c34-b5da-be4186ea2f69'],
]);
