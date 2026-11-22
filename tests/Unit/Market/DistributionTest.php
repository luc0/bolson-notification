<?php

use App\Domains\Market\Support\Distribution;

it('calculates median, average and percentiles', function () {
    $distribution = new Distribution([400, 100, 300, 200]);

    expect($distribution->count())->toBe(4)
        ->and($distribution->median())->toBe(250.0)
        ->and($distribution->average())->toBe(250.0)
        ->and($distribution->percentile(25))->toBe(175.0)
        ->and($distribution->percentile(75))->toBe(325.0);
});

it('handles a single value', function () {
    $distribution = new Distribution([500]);

    expect($distribution->median())->toBe(500.0)
        ->and($distribution->percentile(25))->toBe(500.0);
});

it('needs at least one value', function () {
    new Distribution([]);
})->throws(InvalidArgumentException::class);
