<?php

namespace App\Integrations\ArgentinaDatos;

use Illuminate\Support\Facades\Http;

/**
 * Public API with the daily history of the dollar in Argentina (https://argentinadatos.com).
 */
class ArgentinaDatosClient
{
    /**
     * Every daily quote of a dollar type, oldest first.
     *
     * @param  string  $dollar  "blue", "oficial", "bolsa"...
     * @return array<int, array{date: string, sell: float}>
     */
    public function dollarRates(string $dollar): array
    {
        $quotes = Http::timeout(30)
            ->get(config('services.argentinadatos.url')."/cotizaciones/dolares/{$dollar}")
            ->throw()
            ->json();

        return array_map(fn (array $quote) => ['date' => $quote['fecha'], 'sell' => (float) $quote['venta']], $quotes);
    }
}
