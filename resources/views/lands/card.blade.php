{{-- resources/views/lands/card.blade.php --}}
@php
    $isUnavailable = !$land->active;
    $area = $land->formattedArea();
    $pricePerSquareMeter = $land->formattedPricePerSquareMeter();
    $features = $land->features();
@endphp
<div @class(['card overflow-hidden', 'card-inactive' => $isUnavailable])>
    <div class="p-6">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            @if($isUnavailable)
                <span class="bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 text-xs font-medium px-2.5 py-0.5 rounded" title="Ya no aparece en el sitio de la inmobiliaria">🚫 No disponible</span>
            @endif
            <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ $land->created_at->format('d/m/Y H:i') }}">Detectado {{ $land->detectedForHumans() }}</span>
        </div>

        <h3 class="card-title mb-3">
            <a href="{{ $land->source }}" class="hover:text-blue-600 hover:underline transition-colors duration-200" target="_blank" rel="noopener noreferrer">
                {{ $land->content }}
            </a>
        </h3>

        @if($area)
            <ul class="card-meta mb-3 space-y-0.5">
                <li>📐 {{ $area }}@if($pricePerSquareMeter) · {{ $pricePerSquareMeter }}@endif</li>
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
            @if($land->locationLabel())
                <span class="card-location text-sm" title="{{ $land->location }}">📍 {{ $land->locationLabel() }}</span>
            @else
                <span></span>
            @endif
            @if($land->price_amount)
                <span class="text-xl font-bold text-green-600 whitespace-nowrap">{{ $land->formattedPrice() }}</span>
            @else
                <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $land->formattedPrice() ?? 'Consultar precio' }}</span>
            @endif
        </div>
    </div>
</div>
