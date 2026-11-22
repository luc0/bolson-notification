<?php

namespace App\Domains\Market\Services;

use App\Domains\Market\Models\ExchangeRate;
use App\Integrations\ArgentinaDatos\ArgentinaDatosClient;
use Carbon\CarbonImmutable;

/**
 * Monthly average of the dollar set in config('market.dollar'), stored in exchange_rates.
 */
class ExchangeRates
{
    public function __construct(private readonly ArgentinaDatosClient $client) {}

    /**
     * Downloads the daily quotes and stores the average of each month (the current one too, partial).
     *
     * @return int months stored.
     */
    public function sync(): int
    {
        $dollar = config('market.dollar');
        $monthlyAverages = collect($this->client->dollarRates($dollar))
            ->groupBy(fn (array $quote) => substr($quote['date'], 0, 7))
            ->map(fn ($quotes) => round($quotes->avg('sell'), 2));

        $monthlyAverages->each(fn (float $average, string $month) => ExchangeRate::updateOrCreate(
            ['period' => "{$month}-01", 'dollar' => $dollar],
            ['ars_per_usd' => $average],
        ));

        return $monthlyAverages->count();
    }

    /**
     * Pesos per dollar in that month, null when it isn't stored.
     */
    public function arsPerUsd(CarbonImmutable $month): ?float
    {
        return ExchangeRate::where('dollar', config('market.dollar'))
            ->whereDate('period', $month->startOfMonth())
            ->value('ars_per_usd');
    }
}
