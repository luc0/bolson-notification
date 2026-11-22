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
        {--month= : Month to calculate (YYYY-MM), default: last month and the current one}
        {--backfill : Recalculate every month since the oldest listing, up to the current one}';

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
     * Runs weekly: the current month (partial, it grows every week) and the last one (so it ends up complete).
     *
     * @return CarbonImmutable[]
     */
    private function months(MarketSnapshot $snapshot): array
    {
        $currentMonth = CarbonImmutable::now()->startOfMonth();
        $lastMonth = $currentMonth->subMonthNoOverflow();

        if ($this->option('month')) {
            return [CarbonImmutable::createFromFormat('!Y-m', $this->option('month'))];
        }

        if (! $this->option('backfill')) {
            return [$lastMonth, $currentMonth];
        }

        $firstMonth = $snapshot->firstMonth();
        if ($firstMonth === null) {
            return [];
        }

        $months = [];
        for ($month = $firstMonth; $month->lessThanOrEqualTo($currentMonth); $month = $month->addMonth()) {
            $months[] = $month;
        }

        return $months;
    }
}
