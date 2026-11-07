<?php

namespace App\Http\Controllers\Concerns;

/**
 * One-click filter chips (`<x-filter-chip>`): each chip is a link to the listing with that filter changed.
 * `$url` receives the changed filters and returns the listing URL.
 */
trait BuildsFilterChips
{
    /**
     * Optional "all" chip + one chip per option that adds or removes it from the selection.
     *
     * @param  array<string, string>  $options  value => label
     * @param  string[]  $selected
     */
    private function toggleChips(string $parameter, ?string $allLabel, array $options, array $selected, callable $url): array
    {
        $optionChips = collect($options)->map(function (string $label, string|int $value) use ($parameter, $selected, $url) {
            $value = (string) $value;
            $isSelected = in_array($value, $selected, true);
            $toggled = $isSelected ? array_diff($selected, [$value]) : [...$selected, $value];

            return ['label' => $label, 'url' => $url([$parameter => $toggled]), 'active' => $isSelected];
        })->values()->all();

        if ($allLabel === null) {
            return $optionChips;
        }

        return [['label' => $allLabel, 'url' => $url([$parameter => []]), 'active' => empty($selected)], ...$optionChips];
    }

    /**
     * One option at a time: clicking the active one clears it.
     *
     * @param  array<string, string>  $options  value => label
     */
    private function singleChoiceChips(string $parameter, array $options, ?string $selected, callable $url): array
    {
        return collect($options)->map(function (string $label, string $value) use ($parameter, $selected, $url) {
            $isSelected = $value === $selected;

            return ['label' => $label, 'url' => $url([$parameter => $isSelected ? null : $value]), 'active' => $isSelected];
        })->values()->all();
    }

    private function switchChip(string $parameter, string $label, bool $isOn, callable $url): array
    {
        return ['label' => ($isOn ? '✓ ' : '').$label, 'url' => $url([$parameter => ! $isOn]), 'active' => $isOn];
    }
}
