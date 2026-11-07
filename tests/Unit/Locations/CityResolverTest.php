<?php

use App\Domains\Locations\Enums\City;
use App\Domains\Locations\Support\CityResolver;

it('resolves city and neighborhood from real listing texts', function (array $texts, ?City $city, ?string $neighborhood) {
    $resolved = (new CityResolver)->resolve($texts);

    expect($resolved->city)->toBe($city)
        ->and($resolved->neighborhood)->toBe($neighborhood);
})->with([
    'city with province' => [['El Bolsón, Rio Negro'], City::ElBolson, null],
    'without accent' => [['El Bolson'], City::ElBolson, null],
    'address' => [['AVDA. SAN MARTIN al 2500, El Bolsón'], City::ElBolson, null],
    'city and neighborhood' => [['El Bolsón, Mallín Ahogado'], City::ElBolson, 'Mallín Ahogado'],
    'neighborhood in uppercase' => [['MALLIN AHOGADO S/N, El Bolsón'], City::ElBolson, 'Mallín Ahogado'],
    'neighborhood only' => [['Villa Turismo'], City::ElBolson, 'Villa Turismo'],
    'lago puelo' => [['Lago Puelo, Centro'], City::LagoPuelo, null],
    'lago puelo paraje' => [['Paraje Entre Ríos, Lago Puelo'], City::LagoPuelo, 'Entre Ríos'],
    'lago puelo department' => [['Lago Puelo, Cushamen'], City::LagoPuelo, null],
    'other cities' => [['Casa en El Hoyo'], City::ElHoyo, null],
    'epuyen with accent' => [['Cabaña en Epuyén'], City::Epuyen, null],
    'unknown' => [['Centro'], null, null],
    'falls back to the title' => [['Centro', 'Departamento en Alquiler en Centro, El Bolson'], City::ElBolson, null],
    'first text wins' => [['Lago Puelo', 'a 15 km de El Bolsón'], City::LagoPuelo, null],
    'first city in the text wins' => [['Lago Puelo, a 15 km de El Bolsón'], City::LagoPuelo, null],
    'empty' => [[null, ''], null, null],
    'neighborhood name of another city' => [['Lago Puelo, local sobre Av. Arrayanes'], City::LagoPuelo, null],
]);

it('does not match partial words', function () {
    expect((new CityResolver)->resolve(['Bolsonero'])->city)->toBeNull();
});
