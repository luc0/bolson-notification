<?php

namespace App\Domains\Market\Data;

/**
 * A size range of a series ("Hasta 1.000 m²"): from (exclusive) / to (inclusive), null = open ended.
 * The key is stored in price_stats.size_range and used in URLs.
 */
final readonly class SizeRange
{
    public function __construct(
        public ?float $from,
        public ?float $to,
    ) {}

    /**
     * "0-1000", "1000-5000", "5000+".
     */
    public function key(): string
    {
        $from = $this->number($this->from ?? 0);

        return $this->to === null ? "{$from}+" : "{$from}-{$this->number($this->to)}";
    }

    public function label(): string
    {
        $from = $this->formatted($this->from);
        $to = $this->formatted($this->to);

        return match (true) {
            $this->from === null => "Hasta {$to} m²",
            $this->to === null => "Más de {$from} m²",
            default => "{$from} a {$to} m²",
        };
    }

    public function contains(float $size): bool
    {
        $isAboveFrom = $this->from === null || $size > $this->from;
        $isUpToTo = $this->to === null || $size <= $this->to;

        return $isAboveFrom && $isUpToTo;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function formatted(?float $value): string
    {
        return number_format($value ?? 0, 0, ',', '.');
    }
}
