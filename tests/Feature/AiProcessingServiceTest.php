<?php

use App\Services\AiProcessingService;
use App\Domains\Sources\Data\Source;
use App\Domains\Sources\Enums\SourceType;
use Illuminate\Support\Facades\Http;

function fakeGroqResponse(string $content, string $finishReason = 'stop'): void
{
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => $content], 'finish_reason' => $finishReason]],
        ]),
    ]);
}

$site = new Source('example', 'Example', SourceType::Agency, 'https://example.com', 'https://example.com/p/');

it('returns the rentals array and requests a strict json schema', function () use ($site) {
    fakeGroqResponse(json_encode(['rentals' => [['Content' => 'Depto', 'Link' => 'https://example.com/p/1', 'Price' => 'Consultar']]]));

    $result = app(AiProcessingService::class)->process($site, '<html></html>');

    expect($result)->toHaveCount(1)
        ->and($result[0]['Price'])->toBe('Consultar');

    Http::assertSent(fn ($request) => $request['response_format']['type'] === 'json_schema'
        && $request['messages'][0]['role'] === 'system');
});

it('returns null when the AI request fails', function () use ($site) {
    Http::fake(['api.groq.com/*' => Http::response(['error' => 'model_not_found'], 404)]);

    expect(app(AiProcessingService::class)->process($site, '<html></html>'))->toBeNull();
});

it('returns null when the model refuses', function () use ($site) {
    fakeGroqResponse('I’m sorry, but I can’t provide that.');

    expect(app(AiProcessingService::class)->process($site, '<html></html>'))->toBeNull();
});

it('returns null when the model returns no rentals', function () use ($site) {
    fakeGroqResponse('{"rentals": []}');

    expect(app(AiProcessingService::class)->process($site, '<html></html>'))->toBeNull();
});

it('returns null when the response was truncated', function () use ($site) {
    fakeGroqResponse('{"rentals": [{"Content": "Dep', 'length');

    expect(app(AiProcessingService::class)->process($site, '<html></html>'))->toBeNull();
});

it('retries once when the output does not match the schema', function () use ($site) {
    Http::fakeSequence('api.groq.com/*')
        ->push(['error' => ['code' => 'json_validate_failed']], 400)
        ->push(['choices' => [['message' => ['content' => '{"rentals": [{"Link": "https://example.com/p/1"}]}'], 'finish_reason' => 'stop']]]);

    expect(app(AiProcessingService::class)->process($site, '<html></html>'))->toHaveCount(1);
    Http::assertSentCount(2);
});

it('does not retry other errors', function () use ($site) {
    Http::fake(['api.groq.com/*' => Http::response(['error' => ['code' => 'model_not_found']], 404)]);

    expect(app(AiProcessingService::class)->process($site, '<html></html>'))->toBeNull();
    Http::assertSentCount(1);
});
