<?php

namespace App\Domains\Market\Support;

use InvalidArgumentException;

/**
 * Summary of a list of prices: median, average and percentiles (linear interpolation).
 */
final readonly class Distribution
{
    /** @var float[] sorted ascending */
    private array $values;

    /**
     * @param  float[]  $values
     */
    public function __construct(array $values)
    {
        if ($values === []) {
            throw new InvalidArgumentException('A distribution needs at least one value.');
        }

        sort($values);
        $this->values = array_values($values);
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function median(): float
    {
        return $this->percentile(50);
    }

    public function average(): float
    {
        return array_sum($this->values) / $this->count();
    }

    /**
     * @param  float  $percent  0 to 100.
     */
    public function percentile(float $percent): float
    {
        $position = ($this->count() - 1) * $percent / 100;
        $lowerIndex = (int) floor($position);
        $upperIndex = (int) ceil($position);
        $weight = $position - $lowerIndex;

        return $this->values[$lowerIndex] + ($this->values[$upperIndex] - $this->values[$lowerIndex]) * $weight;
    }
}
