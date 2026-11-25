{{-- resources/views/houses/card.blade.php --}}
@php
    $isUnavailable = !$house->active;
    $specs = $house->specs();
    $features = $house->features();
@endphp
<div @class(['card overflow-hidden', 'card-inactive' => $isUnavailable])>
    <div class="p-6">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            @if($isUnavailable)
                <span class="bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 text-xs font-medium px-2.5 py-0.5 rounded" title="La inmobiliaria ya no la ofrece">🚫 No disponible</span>
            @endif
            @if($house->hasPriceDrop())
                <span class="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs font-medium px-2.5 py-0.5 rounded" title="Antes: {{ $house->previousPrice()->formattedPrice() }}">📉 Bajó de precio</span>
            @endif
            <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ $house->created_at->format('d/m/Y H:i') }}">Publicada {{ $house->detectedForHumans() }}</span>
        </div>

        <h3 class="card-title mb-3">
            <a href="{{ $house->source }}" class="hover:text-blue-600 hover:underline transition-colors duration-200" target="_blank" rel="noopener noreferrer">
                {{ $house->content }}
            </a>
        </h3>

        @if(!empty($specs))
            <ul class="card-meta mb-3 space-y-0.5">
                @foreach($specs as $spec)
                    <li>{{ $spec }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty($features))
            <ul class="card-description mb-4 space-y-0.5">
                @foreach($features as $feature)
                    <li>{{ $feature }}</li>
                @endforeach
            </ul>
        @endif

        <div class="flex items-center justify-between gap-3">
            @if($house->locationLabel())
                <span class="card-location text-sm" title="{{ $house->location }}">📍 {{ $house->locationLabel() }}</span>
            @else
                <span></span>
            @endif
            @if($house->price_amount)
                <span class="text-right whitespace-nowrap">
                    @if($house->hasPriceDrop())
                        <span class="block text-sm text-gray-500 dark:text-gray-400 line-through" title="Precio anterior">{{ $house->previousPrice()->formattedPrice() }}</span>
                    @endif
                    <span class="text-xl font-bold text-green-600">{{ $house->formattedPrice() }}</span>
                </span>
            @else
                <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $house->formattedPrice() ?? 'Consultar precio' }}</span>
            @endif
        </div>
    </div>
</div>
