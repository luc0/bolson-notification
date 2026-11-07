<?php

use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Support\PropertyUseResolver;

it('resolves the property use from real titles', function (array $texts, ?PropertyUse $expected) {
    expect((new PropertyUseResolver)->resolve($texts))->toBe($expected);
})->with([
    'house' => [['Casa en Alquiler en El Bolson, Rio Negro'], PropertyUse::Residential],
    'apartment uppercase' => [['EN ALQUILER DEPARTAMENTO DE DOS DORMITORIOS'], PropertyUse::Residential],
    'short apartment' => [['ALQUILER PERMANENTE, EL BOLSÓN, Dpto. de 1 dorm.'], PropertyUse::Residential],
    'cabin with accent' => [['Cabaña en Lago Puelo'], PropertyUse::Residential],
    'duplex with accent' => [['Dúplex de 2 dormitorios'], PropertyUse::Residential],
    'store' => [['Local en Alquiler en El Bolson, Rio Negro'], PropertyUse::Commercial],
    'commercial hall' => [['SE ALQUILA LOCAL COMERCIAL EN GALERÍA'], PropertyUse::Commercial],
    'offices plural' => [['SE ALQUILAN 3 OFICINAS EN EL CENTRO DE EL BOLSÓN'], PropertyUse::Commercial],
    'first type wins' => [['Casa con local al frente'], PropertyUse::Residential],
    'unknown' => [['SE ALQUILA IMPORTANTE PROPIEDAD EN EL BOLSÓN'], null],
    'falls back to the description' => [['650 MTS2', 'Galpón con oficina'], PropertyUse::Commercial],
    'neighborhood is not a type' => [['Alquiler en Las Quintas'], null],
]);
