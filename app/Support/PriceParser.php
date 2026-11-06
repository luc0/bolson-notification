<?php

namespace App\Support;

class PriceParser
{
    private const USD_PATTERN = '/u\$s|u\$d|us\$|usd|d[oó]lar/i';

    public function parse(mixed $rawPrice): ParsedPrice
    {
        $isEmpty = $rawPrice === null || trim((string) $rawPrice) === '';
        if ($isEmpty) {
            return new ParsedPrice(null, null, null);
        }

        $raw = trim((string) $rawPrice);
        $amount = $this->extractAmount($raw);

        if ($amount === null) {
            return new ParsedPrice($raw, null, null);
        }

        return new ParsedPrice($raw, $amount, $this->detectCurrency($raw));
    }

    private function extractAmount(string $raw): ?float
    {
        $hasDigits = preg_match('/\d/', $raw) === 1;
        if (!$hasDigits) {
            return null;
        }

        // Argentine format: "." for thousands, "," for decimals.
        $numeric = preg_replace('/[^\d,]/', '', $raw);
        $numeric = str_replace(',', '.', $numeric);

        return (float) $numeric;
    }

    private function detectCurrency(string $raw): string
    {
        $isUsd = preg_match(self::USD_PATTERN, $raw) === 1;
        if ($isUsd) {
            return 'USD';
        }

        return 'ARS';
    }
}
