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
                <span class="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-xs font-medium px-2.5 py-0.5 rounded" title="Detectado hace {{ \App\Domains\Rentals\Models\Rental::OLD_AFTER_MONTHS }} meses o más: probablemente ya no esté disponible">⏳ Antiguo</span>
            @endif
            @if($rental->property_use === \App\Domains\Rentals\Enums\PropertyUse::Commercial)
                <span class="bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 text-xs font-medium px-2.5 py-0.5 rounded">🏪 {{ $rental->property_use->label() }}</span>
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
        
        @php
            $features = \App\Domains\Rentals\Support\RentalFeatures::extract($rental->description, $rental->content);
            $specs = $rental->specs();
            $services = $rental->serviceLabels();
        @endphp

        @if(!empty($specs))
            <ul class="card-meta mb-3 space-y-0.5">
                @foreach($specs as $spec)
                    <li>{{ $spec }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty($services))
            <div class="mb-3" title="Servicios informados por la inmobiliaria">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Servicios</span>
                <div class="flex flex-wrap gap-1.5 mt-1">
                    @foreach($services as $service)
                        <span class="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs font-medium px-2 py-0.5 rounded-full">✓ {{ $service }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!empty($features))
            <ul class="card-description mb-4 space-y-0.5">
                @foreach($features as $feature)
                    <li>{{ $feature }}</li>
                @endforeach
            </ul>
        @endif

        <div class="flex items-center justify-between">
            <div class="flex flex-col">
                @if($rental->locationLabel())
                    <span class="card-location text-sm" title="{{ $rental->location }}">📍 {{ $rental->locationLabel() }}</span>
                @endif
                @if($rental->source_path)
                    <span class="card-source">Fuente: {{ $rental->source_path }}</span>
                @endif
            </div>
            @if($rental->price_amount)
                <span class="text-xl font-bold text-green-600">{{ $rental->formattedPrice() }}/mes</span>
            @else
                <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $rental->formattedPrice() ?? 'Consultar precio' }}</span>
            @endif
        </div>
    </div>
</div>
