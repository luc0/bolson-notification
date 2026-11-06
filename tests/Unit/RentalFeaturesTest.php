<?php

use App\Support\RentalFeatures;

it('keeps only extra features with a value', function (?string $description, array $expected) {
    expect(RentalFeatures::extract($description))->toBe($expected);
})->with([
    'labelled, all redundant' => ["🌎 ubicación: El Bolsón\n💸 precio: U\$S550\n🏠 ambientes: 1\n🚿 baños: 0", []],
    'emoji only, zero surface' => ["🏠 0 m²\n🛏️ 3\n📍 El Bolsón, Rio Negro\n💰 \$420.000", []],
    'real surface is kept' => ["🏠 45 m²\n🛏️ 3\n💰 \$420.000", ['🏠 45 m²']],
    'null and N/A' => ["📍 ubicación: El Bolsón\n💰 precio: N/A\n🛋️ ambientes: 0\n🛁 baños: null", []],
    'extras are kept' => ["📍 ubicación: Centro\n📏 superficie: 74m²\n🚗 cochera: sí\n🔥 calefacción: -", ['📏 superficie: 74m²', '🚗 cochera: sí']],
    'literal \\n from old rows' => ['📏 superficie: 30m²\n💸 precio: U$S450', ['📏 superficie: 30m²']],
    'null description' => [null, []],
    'room counts without label' => ["🏠 1 Dormitorio\n🛁 2 Baños\n🛋️ Amueblado", ['🛋️ Amueblado']],
]);

it('drops features that repeat the title', function () {
    $description = "🏠 Casa en Alquiler en El Bolson, Rio Negro\n📏 superficie: 60m²";

    expect(RentalFeatures::extract($description, 'Casa en Alquiler en El Bolson, Rio Negro El Bolsón, Barrio Costanera'))
        ->toBe(['📏 superficie: 60m²']);
});
