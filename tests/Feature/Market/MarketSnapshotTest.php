<?php

use App\Domains\Houses\Models\House;
use App\Domains\Lands\Models\Land;
use App\Domains\Locations\Enums\City;
use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Data\PricedListing;
use App\Domains\Market\Data\SizeRanges;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Enums\PriceUnit;
use App\Domains\Market\Models\ExchangeRate;
use App\Domains\Market\Models\PriceStat;
use App\Domains\Market\Services\PriceStatsCalculator;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Models\Rental;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

function marketRental(float $amount, string $currency = 'ARS', array $attributes = []): Rental
{
    static $count = 0;
    $count++;

    $rental = Rental::create([
        'source' => "https://example.com/rental/{$count}", 'content' => 'Casa', 'description' => '', 'site_path' => 'https://example.com',
        'price_amount' => $amount, 'price_currency' => $currency, 'property_use' => PropertyUse::Residential,
        ...$attributes,
    ]);
    $rental->forceFill(['created_at' => $attributes['created_at'] ?? '2026-10-10'])->save();

    return $rental;
}

function marketStat(string $series, string $currency, ?string $city = null, string $period = '2026-10-01'): ?PriceStat
{
    return PriceStat::where('series', $series)->where('currency', $currency)->whereDate('period', $period)
        ->when($city, fn ($query) => $query->where('city', $city), fn ($query) => $query->whereNull('city'))
        ->whereNull('bedrooms')
        ->whereNull('size_range')
        ->first();
}

beforeEach(function () {
    config(['market.dollar' => 'blue']);
    Http::fake(['api.argentinadatos.com/*' => Http::response([
        ['casa' => 'blue', 'compra' => 990, 'venta' => 1000, 'fecha' => '2026-10-01'],
        ['casa' => 'blue', 'compra' => 1190, 'venta' => 1200, 'fecha' => '2026-10-31'],
        ['casa' => 'blue', 'compra' => 1290, 'venta' => 1300, 'fecha' => '2026-11-02'],
    ])]);
    $this->travelTo(CarbonImmutable::parse('2026-11-22'));
});

it('stores the monthly average of the dollar', function () {
    $this->artisan('market:snapshot')->assertSuccessful();

    expect(ExchangeRate::where('dollar', 'blue')->orderBy('period')->pluck('ars_per_usd')->all())->toBe([1100.0, 1300.0]);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/cotizaciones/dolares/blue'));
});

it('stores last month stats of rentals in pesos and dollars, converting each listing', function () {
    marketRental(500000);
    marketRental(700000);
    marketRental(1100, 'USD'); // 1.210.000 pesos at 1100
    marketRental(900000, 'ARS', ['created_at' => '2026-11-05']); // this month: in its own (partial) stats

    $this->artisan('market:snapshot')->expectsOutputToContain('2026-10:')->expectsOutputToContain('2026-11:')->assertSuccessful();

    expect(marketStat('residential_rentals', 'ARS'))
        ->listings_count->toBe(3)
        ->median->toBe(700000.0)
        ->p25->toBe(600000.0)
        ->p75->toBe(955000.0)
        ->unit->toBe(PriceUnit::Price)
        ->and(marketStat('residential_rentals', 'USD')->median)->toBe(round(700000 / 1100, 2))
        ->and(marketStat('residential_rentals', 'ARS', period: '2026-11-01'))->listings_count->toBe(1)->median->toBe(900000.0);
});

it('runs weekly, recalculating the current month as it grows', function () {
    marketRental(500000, 'ARS', ['created_at' => '2026-11-05']);
    $this->artisan('market:snapshot')->assertSuccessful();

    $this->travelTo(CarbonImmutable::parse('2026-11-29'));
    marketRental(700000, 'ARS', ['created_at' => '2026-11-25']);
    $this->artisan('market:snapshot')->assertSuccessful();

    expect(marketStat('residential_rentals', 'ARS', period: '2026-11-01')->listings_count)->toBe(2);

    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'market:snapshot'));
    expect($event->expression)->toBe('0 4 * * 2');
});

it('keeps residential and commercial rentals in different series', function () {
    marketRental(500000);
    marketRental(2000000, 'ARS', ['property_use' => PropertyUse::Commercial]);
    marketRental(800000, 'ARS', ['property_use' => null]);

    $this->artisan('market:snapshot')->assertSuccessful();

    expect(marketStat('residential_rentals', 'ARS'))->listings_count->toBe(1)->median->toBe(500000.0)
        ->and(marketStat('commercial_rentals', 'ARS'))->listings_count->toBe(1)->median->toBe(2000000.0);
});

it('stores a row per city besides the whole Comarca', function () {
    marketRental(500000, 'ARS', ['city' => City::ElBolson]);
    marketRental(700000, 'ARS', ['city' => City::LagoPuelo]);
    marketRental(900000);

    $this->artisan('market:snapshot')->assertSuccessful();

    expect(marketStat('residential_rentals', 'ARS')->listings_count)->toBe(3)
        ->and(marketStat('residential_rentals', 'ARS', 'el_bolson')->median)->toBe(500000.0)
        ->and(marketStat('residential_rentals', 'ARS', 'lago_puelo')->median)->toBe(700000.0);
});

it('stores houses per built m² in dollars only and lands per m²', function () {
    $house = House::create(['source' => 'https://example.com/h', 'content' => 'Casa', 'site_path' => 'x', 'price_amount' => 120000, 'price_currency' => 'USD', 'covered_area' => 100]);
    $house->forceFill(['created_at' => '2026-10-10'])->save();
    $houseWithoutArea = House::create(['source' => 'https://example.com/h2', 'content' => 'Casa', 'site_path' => 'x', 'price_amount' => 90000, 'price_currency' => 'USD']);
    $houseWithoutArea->forceFill(['created_at' => '2026-10-10'])->save();
    $land = Land::create(['source' => 'https://example.com/l', 'content' => 'Lote', 'site_path' => 'x', 'price_amount' => 30000, 'price_currency' => 'USD', 'area' => 1000]);
    $land->forceFill(['created_at' => '2026-10-10'])->save();
    $landWithoutArea = Land::create(['source' => 'https://example.com/l2', 'content' => 'Lote', 'site_path' => 'x', 'price_amount' => 50000, 'price_currency' => 'USD']);
    $landWithoutArea->forceFill(['created_at' => '2026-10-10'])->save();

    $this->artisan('market:snapshot')->assertSuccessful();

    expect(marketStat('houses_for_sale', 'USD'))->median->toBe(1200.0)->listings_count->toBe(1)->unit->toBe(PriceUnit::PricePerCoveredSquareMeter)
        ->and(marketStat('houses_for_sale', 'ARS'))->toBeNull()
        ->and(marketStat('lands_for_sale', 'USD'))->median->toBe(30.0)->listings_count->toBe(1)->unit->toBe(PriceUnit::PricePerSquareMeter);
});

it('replaces the stats of a month when it runs again', function () {
    marketRental(500000);
    $this->artisan('market:snapshot')->assertSuccessful();

    marketRental(700000);
    $this->artisan('market:snapshot')->assertSuccessful();

    expect(PriceStat::where('series', 'residential_rentals')->where('currency', 'ARS')->whereNull('city')->count())->toBe(1)
        ->and(marketStat('residential_rentals', 'ARS')->listings_count)->toBe(2);
});

it('backfills every month since the oldest listing', function () {
    marketRental(400000, 'ARS', ['created_at' => '2026-08-15']);
    marketRental(500000);

    $this->artisan('market:snapshot --backfill')
        ->expectsOutputToContain('2026-08:')
        ->expectsOutputToContain('2026-09: 0 stats.')
        ->expectsOutputToContain('2026-10:')
        ->expectsOutputToContain('2026-11: 0 stats.')
        ->assertSuccessful();

    expect(PriceStat::whereNull('city')->where('currency', 'ARS')->where('series', 'residential_rentals')->count())->toBe(2);
});

it('calculates a given month', function () {
    marketRental(400000, 'ARS', ['created_at' => '2026-08-15']);

    $this->artisan('market:snapshot --month=2026-08')->expectsOutputToContain('2026-08:')->assertSuccessful();

    expect(marketStat('residential_rentals', 'ARS', period: '2026-08-01'))->not->toBeNull();
});

it('keeps going with the stored rates when the dollar API fails, leaving out what it cannot convert', function () {
    // Http::fake() keeps the first stub registered: point the client to another host.
    config(['services.argentinadatos.url' => 'https://down.example.com']);
    Http::fake(['down.example.com/*' => Http::response('down', 500)]);
    marketRental(500000);
    marketRental(1000, 'USD');

    $this->artisan('market:snapshot')->expectsOutputToContain('Could not update the dollar rates')->assertSuccessful();

    expect(marketStat('residential_rentals', 'ARS')->listings_count)->toBe(1)
        ->and(marketStat('residential_rentals', 'USD')->listings_count)->toBe(1);
});

it('groups by bedrooms when the series asks for it, with 4 or more together', function () {
    $series = new class implements PriceSeries
    {
        public function key(): string
        {
            return 'test';
        }

        public function label(): string
        {
            return 'Test';
        }

        public function unit(): PriceUnit
        {
            return PriceUnit::Price;
        }

        public function currencies(): array
        {
            return [Currency::Usd];
        }

        public function groupsByBedrooms(): bool
        {
            return true;
        }

        public function sizeRanges(): ?SizeRanges
        {
            return null;
        }

        public function listingsPublishedIn(CarbonImmutable $month): iterable
        {
            return [
                new PricedListing(100000, Currency::Usd, bedrooms: 2),
                new PricedListing(200000, Currency::Usd, bedrooms: 4),
                new PricedListing(300000, Currency::Usd, bedrooms: 6),
                new PricedListing(150000, Currency::Usd),
            ];
        }

        public function firstPublishedAt(): ?CarbonImmutable
        {
            return null;
        }
    };

    $rows = collect(app(PriceStatsCalculator::class)->calculate($series, CarbonImmutable::parse('2026-10-01'), null));

    expect($rows->firstWhere('bedrooms', null)['listings_count'])->toBe(4)
        ->and($rows->firstWhere('bedrooms', 2)['median'])->toBe(100000.0)
        ->and($rows->firstWhere('bedrooms', 4))->listings_count->toBe(2)->median->toBe(250000.0)
        ->and($rows->pluck('bedrooms')->all())->toBe([null, 2, 4]);
});

it('stores lands and houses per size range too, leaving out of the ranges the ones without a size', function () {
    config(['market.size_ranges.lands_for_sale' => [1000, 5000]]);
    foreach ([[10000, 500], [20000, 1000], [30000, 3000], [60000, 20000]] as $index => [$price, $area]) {
        $land = Land::create([
            'source' => "https://example.com/land/{$index}", 'content' => 'Lote', 'site_path' => 'https://example.com',
            'price_amount' => $price, 'price_currency' => 'USD', 'area' => $area, 'city' => City::LagoPuelo,
        ]);
        $land->forceFill(['created_at' => '2026-10-10'])->save();
    }
    $house = House::create([
        'source' => 'https://example.com/house/1', 'content' => 'Casa', 'site_path' => 'https://example.com',
        'price_amount' => 90000, 'price_currency' => 'USD',
    ]);
    $house->forceFill(['created_at' => '2026-10-10'])->save();

    $this->artisan('market:snapshot')->assertSuccessful();

    $lands = PriceStat::where('series', 'lands_for_sale')->whereNull('city')->get()->keyBy(fn (PriceStat $stat) => $stat->size_range ?? 'all');
    expect($lands->keys()->sort()->values()->all())->toBe(['0-1000', '1000-5000', '5000+', 'all'])
        ->and($lands['all']->listings_count)->toBe(4)
        ->and($lands['0-1000']->listings_count)->toBe(2)
        ->and($lands['1000-5000']->median)->toBe(10.0)
        ->and($lands['5000+']->median)->toBe(3.0)
        ->and(PriceStat::where('series', 'lands_for_sale')->where('city', 'lago_puelo')->whereNotNull('size_range')->count())->toBe(3);

    expect(PriceStat::where('series', 'houses_for_sale')->count())->toBe(0);
});

it('labels the size ranges built from the config bounds', function () {
    $ranges = SizeRanges::fromBounds('Superficie', [1000, 5000]);

    expect(array_map(fn ($range) => [$range->key(), $range->label()], $ranges->ranges))->toBe([
        ['0-1000', 'Hasta 1.000 m²'],
        ['1000-5000', '1.000 a 5.000 m²'],
        ['5000+', 'Más de 5.000 m²'],
    ])
        ->and($ranges->rangeOf(1000)->key())->toBe('0-1000')
        ->and($ranges->rangeOf(1000.5)->key())->toBe('1000-5000')
        ->and($ranges->rangeOf(null))->toBeNull();
});
