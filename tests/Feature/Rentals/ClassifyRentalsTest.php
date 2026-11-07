<?php

use App\Domains\Rentals\Enums\City;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Models\Rental;

it('classifies existing rentals without AI', function () {
    config(['sources.rental_sites' => [
        'agency' => ['name' => 'Agency', 'listing_url' => 'https://agency.com/l', 'link_base_url' => 'https://agency.com', 'default_city' => 'el_bolson'],
    ]]);
    $fromText = Rental::create(['source' => 'https://a.com/1', 'content' => 'Local comercial', 'description' => '', 'location' => 'El Bolsón, Mallín Ahogado']);
    $fromSource = Rental::create(['source' => 'https://agency.com/2', 'content' => 'Depto', 'description' => '', 'location' => 'Centro', 'site_path' => 'https://agency.com/l']);
    $unknown = Rental::create(['source' => 'https://b.com/3', 'content' => 'Depto', 'description' => '', 'location' => 'Centro']);

    $this->artisan('rentals:classify')->assertSuccessful();

    expect($fromText->refresh()->city)->toBe(City::ElBolson)
        ->and($fromText->neighborhood)->toBe('Mallín Ahogado')
        ->and($fromText->property_use)->toBe(PropertyUse::Commercial)
        ->and($unknown->refresh()->property_use)->toBe(PropertyUse::Residential)
        ->and($fromSource->refresh()->city)->toBe(City::ElBolson)
        ->and($unknown->refresh()->city)->toBeNull();
});
