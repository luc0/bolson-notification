<?php

use App\Domains\Rentals\Enums\City;
use App\Domains\Rentals\Models\Rental;

it('resolves the city of existing rentals without AI', function () {
    config(['sources.sites' => [
        'agency' => ['name' => 'Agency', 'listing_url' => 'https://agency.com/l', 'link_base_url' => 'https://agency.com', 'default_city' => 'el_bolson'],
    ]]);
    $fromText = Rental::create(['source' => 'https://a.com/1', 'content' => 'Depto', 'description' => '', 'location' => 'El Bolsón, Mallín Ahogado']);
    $fromSource = Rental::create(['source' => 'https://agency.com/2', 'content' => 'Depto', 'description' => '', 'location' => 'Centro', 'site_path' => 'https://agency.com/l']);
    $unknown = Rental::create(['source' => 'https://b.com/3', 'content' => 'Depto', 'description' => '', 'location' => 'Centro']);

    $this->artisan('rentals:resolve-cities')->assertSuccessful();

    expect($fromText->refresh()->city)->toBe(City::ElBolson)
        ->and($fromText->neighborhood)->toBe('Mallín Ahogado')
        ->and($fromSource->refresh()->city)->toBe(City::ElBolson)
        ->and($unknown->refresh()->city)->toBeNull();
});
