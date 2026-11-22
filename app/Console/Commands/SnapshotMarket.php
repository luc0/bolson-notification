<?php

namespace App\Console\Commands;

use App\Domains\Market\Services\ExchangeRates;
use App\Domains\Market\Services\MarketSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class SnapshotMarket extends Command
{
    protected $signature = 'market:snapshot
        {--month= : Month to calculate (YYYY-MM), default: last month}
        {--backfill : Recalculate every month since the oldest listing}';

    protected $description = 'Stores the monthly price stats of the Análisis section (listings published each month)';

    public function handle(ExchangeRates $exchangeRates, MarketSnapshot $snapshot): int
    {
        $this->syncExchangeRates($exchangeRates);

        foreach ($this->months($snapshot) as $month) {
            $rowCount = $snapshot->store($month);
            $this->line("{$month->format('Y-m')}: {$rowCount} stats.");
        }

        return self::SUCCESS;
    }

    /**
     * Without new rates the stored ones are used: only listings in another currency are left out.
     */
    private function syncExchangeRates(ExchangeRates $exchangeRates): void
    {
        try {
            $monthCount = $exchangeRates->sync();
            $this->info("Dollar rates updated ({$monthCount} months).");
        } catch (Throwable $e) {
            $this->warn('Could not update the dollar rates, using the stored ones: '.$e->getMessage());
            report($e);
        }
    }

    /**
     * @return CarbonImmutable[]
     */
    private function months(MarketSnapshot $snapshot): array
    {
        $lastMonth = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        if ($this->option('month')) {
            return [CarbonImmutable::createFromFormat('!Y-m', $this->option('month'))];
        }

        if (! $this->option('backfill')) {
            return [$lastMonth];
        }

        $firstMonth = $snapshot->firstMonth();
        if ($firstMonth === null || $firstMonth->greaterThan($lastMonth)) {
            return [];
        }

        $months = [];
        for ($month = $firstMonth; $month->lessThanOrEqualTo($lastMonth); $month = $month->addMonth()) {
            $months[] = $month;
        }

        return $months;
    }
}
