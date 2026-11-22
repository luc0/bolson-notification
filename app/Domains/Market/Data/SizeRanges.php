<?php

namespace App\Domains\Market\Data;

/**
 * Size ranges a series stores its stats in besides every size, so places are compared with similar
 * listings (e.g. small plots against small plots). Built from the bounds in config/market.php.
 */
final readonly class SizeRanges
{
    /**
     * @param  string  $label  what is measured, shown before the chips ("Superficie").
     * @param  SizeRange[]  $ranges
     */
    public function __construct(
        public string $label,
        public array $ranges,
    ) {}

    /**
     * [1000, 5000] → up to 1000, 1000 to 5000, more than 5000.
     *
     * @param  array<int, int|float>  $bounds  ascending.
     */
    public static function fromBounds(string $label, array $bounds): self
    {
        $edges = [null, ...array_map(floatval(...), $bounds), null];
        $ranges = array_map(
            fn (int $index) => new SizeRange($edges[$index], $edges[$index + 1]),
            array_keys(array_slice($edges, 1)),
        );

        return new self($label, $ranges);
    }

    public function rangeOf(?float $size): ?SizeRange
    {
        if ($size === null) {
            return null;
        }

        return collect($this->ranges)->first(fn (SizeRange $range) => $range->contains($size));
    }
}
