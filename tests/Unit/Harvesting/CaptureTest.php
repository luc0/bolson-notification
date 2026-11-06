<?php

use App\Domains\Harvesting\Data\Capture;

it('resolves image ids to their real url', function (mixed $imageId, ?string $expected) {
    $capture = new Capture('<img src="#img-0"><img src="#img-1">', ['https://img/0.jpg', 'https://img/1.jpg']);

    expect($capture->imageUrl($imageId))->toBe($expected);
})->with([
    'valid id' => ['#img-1', 'https://img/1.jpg'],
    'with spaces' => [' #img-0 ', 'https://img/0.jpg'],
    'unknown index' => ['#img-9', null],
    'not an id' => ['https://img/0.jpg', null],
    'null' => [null, null],
]);

it('includes the images in the snapshot', function () {
    $capture = new Capture('<html>', ['https://img/0.jpg']);
    $stampedPhoto = new Capture('<html>', ['https://img/0-alquilado.jpg']);

    expect($capture->snapshot())->not->toBe($stampedPhoto->snapshot());
});
