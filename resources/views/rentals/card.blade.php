{{-- resources/views/rentals/card.blade.php --}}
@php
    $isInactive = !$rental->active;
    $isRented = $rental->isRented();
    $isUnavailable = !$rental->isAvailable();
    $isOld = !$isUnavailable && $rental->isOld();
@endphp
<div @class([
    'card overflow-hidden',
    'card-old' => $isOld,
    'card-inactive' => $isUnavailable,
])>
    <div class="p-6">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            @if($isRented)
                <span class="bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 text-xs font-medium px-2.5 py-0.5 rounded" title="La foto del aviso tiene un cartel de alquilado (detectado {{ $rental->rented_at->locale('es')->diffForHumans() }})">🔑 Alquilado</span>
            @endif
            @if($isInactive)
                <span class="bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 text-xs font-medium px-2.5 py-0.5 rounded" title="Ya no aparece en el sitio de la inmobiliaria">🚫 No disponible</span>
            @endif
            @if($isOld)
                <span class="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-xs font-medium px-2.5 py-0.5 rounded" title="Detectado hace {{ \App\Models\Rental::OLD_AFTER_MONTHS }} meses o más: probablemente ya no esté disponible">⏳ Antiguo</span>
            @endif
            <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ $rental->created_at->format('d/m/Y H:i') }}">Detectado {{ $rental->detectedForHumans() }}</span>
        </div>
        <div class="flex justify-between items-start mb-3">
            @if($rental->source)
                <h3 class="card-title">
                    <a href="{{ $rental->source }}" 
                       data-fallback-url="{{ $rental->site_path }}" 
                       class="rental-link hover:text-blue-600 hover:underline transition-colors duration-200" 
                       target="_blank" 
                       rel="noopener noreferrer">
                        {{ $rental->content }}
                    </a>
                </h3>
            @else
                <h3 class="card-title">{{ $rental->content }}</h3>
            @endif
            <!-- <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">{{ $rental->source }}</span> -->
        </div>
        
        <p class="card-description mb-4">{{ $rental->description }}</p>
        
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-4 card-meta">
                @if($rental->rooms)
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1 flex-shrink-0" style="width: 16px; height: 16px;" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        {{ $rental->rooms }} dorm.
                    </span>
                @endif
                @if($rental->bathrooms)
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1 flex-shrink-0" style="width: 16px; height: 16px;" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                        </svg>
                        {{ $rental->bathrooms }} baños
                    </span>
                @endif
            </div>
        </div>
        
        <div class="flex items-center justify-between">
            <div class="flex flex-col">
                @if($rental->location)
                    <span class="card-location text-sm">{{ $rental->location }}</span>
                @endif
                @if($rental->source_path)
                    <span class="card-source">Fuente: {{ $rental->source_path }}</span>
                @endif
            </div>
            @if($rental->price_amount)
                <span class="text-xl font-bold text-green-600">{{ $rental->price_currency === 'USD' ? 'U$S' : '$' }}{{ number_format($rental->price_amount, 0, ',', '.') }}/mes</span>
            @elseif($rental->price)
                <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $rental->price }}</span>
            @endif
        </div>
    </div>
</div>
