<?php

use App\Domains\Harvesting\Data\ImportResult;
use App\Domains\Harvesting\Services\HouseImporter;
use App\Domains\Harvesting\Services\LandImporter;
use App\Domains\Harvesting\Services\RentalImporter;
use App\Domains\Houses\Models\House;
use App\Domains\Lands\Models\Land;
use App\Domains\Rentals\Models\Rental;
use App\Domains\Sources\Data\Source;
use App\Domains\Sources\Enums\SourceType;
use Illuminate\Support\Facades\Log;

function historySource(?string $listingIdPattern = '#/p/(\d+)-#'): Source
{
    return new Source('example', 'Example', SourceType::Agency, 'https://example.com/listado', 'https://example.com/', listingIdPattern: $listingIdPattern);
}

function historyItem(string $link, string $price, array $overrides = []): array
{
    return ['Content' => 'Casa', 'Link' => $link, 'Caracteristicas' => 'Linda casa', 'Price' => $price, 'Location' => 'El Bolsón', ...$overrides];
}

function importRentalsResult(array $items, ?Source $source = null): ImportResult
{
    return app(RentalImporter::class)->import($items, $source ?? historySource());
}

function importRentals(array $items, ?Source $source = null): array
{
    return importRentalsResult($items, $source)->newItems;
}

it('starts the history with the price a rental was found with', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);

    $change = Rental::first()->priceChanges()->sole();
    expect($change)
        ->price_amount->toBe('500000.00')
        ->price_currency->toBe('ARS')
        ->match_method->toBe('created')
        ->match_confidence->toBe(1.0);
});

it('waits for a second read before taking a new price', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);

    $result = importRentalsResult([historyItem('https://example.com/p/10-casa', '$450.000')]);

    $rental = Rental::sole();
    expect($result->pendingPriceCount)->toBe(1)
        ->and($rental->price_amount)->toBe('500000.00')
        ->and($rental->pending_price_amount)->toBe('450000.00')
        ->and($rental->priceChanges()->count())->toBe(1);
});

it('takes the new price when the second read confirms it, and records it', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$450.000')]);

    $result = importRentalsResult([historyItem('https://example.com/p/10-casa', '$450.000')]);

    $rental = Rental::sole();
    expect($result->pendingPriceCount)->toBe(0)
        ->and($result->newItems)->toBeEmpty()
        ->and($rental->price_amount)->toBe('450000.00')
        ->and($rental->pending_price_amount)->toBeNull()
        ->and($rental->priceChanges()->orderBy('id')->pluck('price_amount')->all())->toBe(['500000.00', '450000.00'])
        ->and($rental->priceChanges()->latest('id')->first()->match_method)->toBe('url');
});

it('drops a misread price when the second read gives the known one', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$540.000')]);

    $result = importRentalsResult([historyItem('https://example.com/p/10-casa', '$500.000')]);

    $rental = Rental::sole();
    expect($result->pendingPriceCount)->toBe(0)
        ->and($rental->price_amount)->toBe('500000.00')
        ->and($rental->pending_price_amount)->toBeNull()
        ->and($rental->priceChanges()->count())->toBe(1);
});

it('replaces the pending price when the second read gives a third one', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$540.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$450.000')]);

    expect(Rental::sole())->price_amount->toBe('500000.00')->pending_price_amount->toBe('450000.00');
});

it('keeps the known price and drops the pending one when the price cannot be read', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$450.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', 'Consultar')]);

    expect(Rental::sole())->price_amount->toBe('500000.00')->pending_price_amount->toBeNull()
        ->and(Rental::sole()->priceChanges()->count())->toBe(1);
});

it('drops the pending price of a rental that is no longer listed, so the source is not re-read forever', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000'), historyItem('https://example.com/p/11-depto', '$300.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$450.000'), historyItem('https://example.com/p/11-depto', '$300.000')]);

    $result = importRentalsResult([historyItem('https://example.com/p/11-depto', '$300.000')]);

    expect($result->pendingPriceCount)->toBe(0)
        ->and(Rental::where('external_id', '10')->first())->active->toBeFalse()->pending_price_amount->toBeNull();
});

it('ignores implausible jumps, most likely the AI misreading the price', function () {
    Log::spy();
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$5.000.000')]);

    expect(Rental::sole())->price_amount->toBe('500000.00')->pending_price_amount->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'implausible'))->once();
});

it('takes a confirmed change of currency', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '550', ['Currency' => 'USD'])]);
    importRentals([historyItem('https://example.com/p/10-casa', '550', ['Currency' => 'USD'])]);

    expect(Rental::sole())->price_amount->toBe('550.00')->price_currency->toBe('USD');
});

it('recognizes a rental whose URL changed by the agency id, without notifying it as new', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);

    $newItems = importRentals([historyItem('https://example.com/p/10-casa-con-parque', '$480.000')]);

    $rental = Rental::sole();
    expect($newItems)->toBeEmpty()
        ->and($rental->source)->toBe('https://example.com/p/10-casa-con-parque')
        ->and($rental->external_id)->toBe('10')
        ->and($rental->active)->toBeTrue()
        ->and($rental->price_amount)->toBe('500000.00')
        ->and($rental->pending_price_amount)->toBe('480000.00')
        ->and($rental->priceChanges()->latest('id')->first())
        ->match_method->toBe('external_id')
        ->match_confidence->toBe(1.0);
});

it('records the match when only the URL changed', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    importRentals([historyItem('https://example.com/p/10-casa-nueva', '$500.000')]);

    expect(Rental::sole()->priceChanges()->pluck('match_method')->all())->toBe(['created', 'external_id']);
});

it('stores a new rental when the source has no listing id', function () {
    $source = historySource(listingIdPattern: null);
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')], $source);

    $newItems = importRentals([historyItem('https://example.com/p/10-casa-nueva', '$500.000')], $source);

    expect($newItems)->toHaveCount(1)
        ->and(Rental::count())->toBe(2)
        ->and(Rental::where('source', 'https://example.com/p/10-casa')->first()->active)->toBeFalse();
});

it('only matches the agency id within the same source', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    $otherSource = new Source('other', 'Other', SourceType::Agency, 'https://other.com/listado', 'https://other.com/', listingIdPattern: '#/p/(\d+)-#');

    $newItems = importRentals([historyItem('https://other.com/p/10-depto', '$300.000')], $otherSource);

    expect($newItems)->toHaveCount(1)->and(Rental::count())->toBe(2);
});

it('tracks land prices and URL changes too', function () {
    $importer = app(LandImporter::class);
    $importer->import([historyItem('https://example.com/p/20-lote', 'U$S 30.000', ['Area' => 1000])], historySource());
    $importer->import([historyItem('https://example.com/p/20-lote-rebajado', 'U$S 27.000', ['Area' => 1000])], historySource());

    $result = $importer->import([historyItem('https://example.com/p/20-lote-rebajado', 'U$S 27.000', ['Area' => 1000])], historySource());

    $land = Land::sole();
    expect($result->newItems)->toBeEmpty()
        ->and($result->pendingPriceCount)->toBe(0)
        ->and($land->price_amount)->toBe('27000.00')
        ->and($land->source)->toBe('https://example.com/p/20-lote-rebajado')
        ->and($land->priceChanges()->pluck('match_method')->all())->toBe(['created', 'external_id', 'url'])
        ->and($land->hasPriceDrop())->toBeTrue();
});

it('does not take a placeholder sale price as a price change', function () {
    $importer = app(HouseImporter::class);
    $importer->import([historyItem('https://example.com/p/30-casa', 'U$S 120.000')], historySource());
    $importer->import([historyItem('https://example.com/p/30-casa', 'U$S 200')], historySource());

    expect(House::sole())->price_amount->toBe('120000.00')->pending_price_amount->toBeNull()
        ->and(House::sole()->priceChanges()->count())->toBe(1);
});

it('tracks house prices', function () {
    $importer = app(HouseImporter::class);
    $importer->import([historyItem('https://example.com/p/30-casa', 'U$S 120.000')], historySource());
    $pending = $importer->import([historyItem('https://example.com/p/30-casa', 'U$S 110.000')], historySource());
    $importer->import([historyItem('https://example.com/p/30-casa', 'U$S 110.000')], historySource());

    $house = House::sole();
    expect($pending->pendingPriceCount)->toBe(1)
        ->and($house->price_amount)->toBe('110000.00')
        ->and($house->previousPrice()->formattedPrice())->toBe('U$S 120.000')
        ->and($house->hasPriceDrop())->toBeTrue();
});

it('does not flag a price rise as a drop', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$600.000')]);
    importRentals([historyItem('https://example.com/p/10-casa', '$600.000')]);

    expect(Rental::sole()->hasPriceDrop())->toBeFalse()
        ->and(Rental::sole()->previousPrice()->formattedPrice())->toBe('$500.000');
});

it('matches the active and newest listing when the agency id was stored twice before', function () {
    $old = Rental::create(['source' => 'https://example.com/p/10-viejo', 'external_id' => '10', 'content' => 'Casa', 'description' => '', 'site_path' => 'https://example.com/listado', 'active' => false]);
    $current = Rental::create(['source' => 'https://example.com/p/10-actual', 'external_id' => '10', 'content' => 'Casa', 'description' => '', 'site_path' => 'https://example.com/listado', 'active' => true]);

    importRentals([historyItem('https://example.com/p/10-nuevo', '$500.000')]);

    expect($current->fresh()->source)->toBe('https://example.com/p/10-nuevo')
        ->and($old->fresh()->source)->toBe('https://example.com/p/10-viejo');
});

it('reactivates a listing that comes back within the relisting window, without notifying it', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$500.000'), historyItem('https://example.com/p/11-depto', '$300.000')]);
    importRentals([historyItem('https://example.com/p/11-depto', '$300.000')]);
    expect(Rental::where('external_id', '10')->sole()->deactivated_at)->not->toBeNull();

    $this->travel(10)->days();
    $newItems = importRentals([historyItem('https://example.com/p/10-casa', '$500.000'), historyItem('https://example.com/p/11-depto', '$300.000')]);

    expect($newItems)->toBeEmpty()
        ->and(Rental::count())->toBe(2)
        ->and(Rental::where('external_id', '10')->sole())->active->toBeTrue()->deactivated_at->toBeNull();
});

it('stores a listing that comes back after the relisting window as a new one, even with the same URL', function () {
    config(['harvesting.relisting_after_days' => 30]);
    importRentals([historyItem('https://example.com/p/10-casa', '$450.000'), historyItem('https://example.com/p/11-depto', '$300.000')]);
    importRentals([historyItem('https://example.com/p/11-depto', '$300.000')]);
    $old = Rental::where('external_id', '10')->sole();

    $this->travel(31)->days();
    $newItems = importRentals([historyItem('https://example.com/p/10-casa', '$800.000'), historyItem('https://example.com/p/11-depto', '$300.000')]);

    $relisted = Rental::where('external_id', '10')->latest('id')->first();
    expect(collect($newItems)->pluck('Link')->all())->toBe(['https://example.com/p/10-casa'])
        ->and($relisted->id)->not->toBe($old->id)
        ->and($relisted)->active->toBeTrue()->price_amount->toBe('800000.00')
        ->and($old->fresh())->active->toBeFalse()->price_amount->toBe('450000.00');
});

it('stores a listing relisted with a new URL after the window as a new one', function () {
    importRentals([historyItem('https://example.com/p/10-casa', '$450.000'), historyItem('https://example.com/p/11-depto', '$300.000')]);
    importRentals([historyItem('https://example.com/p/11-depto', '$300.000')]);

    $this->travel(31)->days();
    $newItems = importRentals([historyItem('https://example.com/p/10-casa-2-dormitorios', '$800.000'), historyItem('https://example.com/p/11-depto', '$300.000')]);

    expect($newItems)->toHaveCount(1)
        ->and(Rental::where('external_id', '10')->count())->toBe(2);
});
