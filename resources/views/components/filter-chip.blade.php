@props(['chip'])

<a href="{{ $chip['url'] }}" @class(['filter-chip', 'filter-chip-active' => $chip['active']]) @if($chip['active']) aria-current="true" @endif>{{ $chip['label'] }}</a>
