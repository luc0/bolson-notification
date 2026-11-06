<?php

use App\Support\PriceParser;

it('parses prices returned by the scraper', function (mixed $raw, ?float $amount, ?string $currency) {
    $price = (new PriceParser())->parse($raw);

    expect($price->amount)->toBe($amount)
        ->and($price->currency)->toBe($currency);
})->with([
    'ars with thousands dots' => ['$650.000 ARS', 650000.0, 'ARS'],
    'ars prefix' => ['AR$ 200000', 200000.0, 'ARS'],
    'usd prefix' => ['U$S550', 550.0, 'USD'],
    'usd suffix' => ['950 USD', 950.0, 'USD'],
    'decimals with comma' => ['$1.500,50', 1500.5, 'ARS'],
    'plain number' => [600000, 600000.0, 'ARS'],
    'ask for price' => ['Consultar', null, null],
    'null' => [null, null, null],
    'empty' => ['', null, null],
]);

it('keeps the raw text', function () {
    expect((new PriceParser())->parse('Consultar')->raw)->toBe('Consultar');
});
