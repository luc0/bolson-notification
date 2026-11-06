<?php

use App\Domains\Rentals\Support\PriceParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rows created before price was split only had a number ("550.00") and were migrated as ARS.
 * The original text is still in the description ("💸 precio: U$S550"), so parse it from there.
 */
return new class extends Migration
{
    // "💸 precio: U$S550" / "💰 $490.000" / "precio: 1400 USD" -> captures only the value.
    private const PRICE_LINE_PATTERN = '/(?:💰|💸)?\s*(?:precio\s*:)?\s*((?:u\$s|usd|us\$|ar\$|\$)\s*[\d.,]+(?:\s*usd)?|[\d.,]+\s*usd)/iu';

    public function up(): void
    {
        $parser = new PriceParser();
        $legacyRentals = DB::table('rentals')->whereNotNull('price')->get()
            ->filter(fn ($rental) => preg_match('/^\d+(\.\d+)?$/', $rental->price) === 1);

        foreach ($legacyRentals as $rental) {
            $hasPriceLine = preg_match(self::PRICE_LINE_PATTERN, (string) $rental->description, $matches) === 1;
            if (!$hasPriceLine) {
                continue;
            }

            // Placeholders ("$1") end up with no amount and show as "Consultar precio".
            $parsedPrice = $parser->parse($matches[1]);

            DB::table('rentals')->where('id', $rental->id)->update([
                'price' => $parsedPrice->raw,
                'price_amount' => $parsedPrice->amount,
                'price_currency' => $parsedPrice->currency,
            ]);
        }
    }

    public function down(): void
    {
        // Data fix, nothing to revert.
    }
};
