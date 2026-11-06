<?php

use App\Services\RentalImageChecker;
use Illuminate\Support\Facades\Http;

function fakeVisionAnswer(string $content): void
{
    Http::fake([
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $content]]]]),
    ]);
}

it('detects a rented stamp', function () {
    fakeVisionAnswer('{"rented": true}');

    expect(app(RentalImageChecker::class)->isRented('https://example.com/photo.jpg'))->toBeTrue();

    Http::assertSent(fn ($request) => $request['model'] === config('services.groq.vision_model')
        && $request['messages'][0]['content'][1]['image_url']['url'] === 'https://example.com/photo.jpg');
});

it('detects a free photo', function () {
    fakeVisionAnswer('{"rented": false}');

    expect(app(RentalImageChecker::class)->isRented('https://example.com/photo.jpg'))->toBeFalse();
});

it('returns null on API errors or invalid answers', function (int $status, string $content) {
    Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $content]]]], $status)]);

    expect(app(RentalImageChecker::class)->isRented('https://example.com/photo.jpg'))->toBeNull();
})->with([
    'server error' => [500, '{"rented": true}'],
    'not json' => [200, 'yes'],
    'missing key' => [200, '{"answer": true}'],
]);
