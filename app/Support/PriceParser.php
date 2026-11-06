<?php

namespace App\Support;

class PriceParser
{
    private const USD_PATTERN = '/u\$s|u\$d|us\$|usd|d[oó]lar/i';
    private const KNOWN_CURRENCIES = ['ARS', 'USD'];

    // Agencies use "$1" or "$0" as a placeholder for "ask for the price".
    private const MIN_REAL_AMOUNT = 10;

    /**
     * @param string|null $currencyHint currency given by the AI ("ARS"/"USD"), preferred over detection.
     */
    public function parse(mixed $rawPrice, ?string $currencyHint = null): ParsedPrice
    {
        $isEmpty = $rawPrice === null || trim((string) $rawPrice) === '';
        if ($isEmpty) {
            return new ParsedPrice(null, null, null);
        }

        $raw = trim((string) $rawPrice);
        $amount = $this->extractAmount($raw);

        $isPlaceholderAmount = $amount === null || $amount < self::MIN_REAL_AMOUNT;
        if ($isPlaceholderAmount) {
            return new ParsedPrice($raw, null, null);
        }

        $currency = in_array($currencyHint, self::KNOWN_CURRENCIES, true) ? $currencyHint : $this->detectCurrency($raw);

        return new ParsedPrice($raw, $amount, $currency);
    }

    private function extractAmount(string $raw): ?float
    {
        $hasDigits = preg_match('/\d/', $raw) === 1;
        if (!$hasDigits) {
            return null;
        }

        $numeric = preg_replace('/[^\d.,]/', '', $raw);

        // US format with comma thousands ("1,400" / "1,400.50").
        $isUsFormat = preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $numeric) === 1;
        if ($isUsFormat) {
            return (float) str_replace(',', '', $numeric);
        }

        // Argentine format: "." for thousands, "," for decimals.
        return (float) str_replace(['.', ','], ['', '.'], $numeric);
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
