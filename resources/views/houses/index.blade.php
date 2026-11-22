{{-- resources/views/houses/index.blade.php --}}
@php
    [$availableHouses, $unavailableHouses] = $houses->partition(fn ($house) => $house->active);

    $sections = [
        ['title' => 'Casas en venta', 'subtitle' => null, 'houses' => $availableHouses],
        ['title' => 'No disponibles', 'subtitle' => 'Vendidas o dadas de baja por la inmobiliaria.', 'houses' => $unavailableHouses],
    ];
@endphp
<x-app-layout>
    <div class="min-h-screen">
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-3">Casas en venta en la Comarca Andina</h1>

            {{-- One-click filters: each chip is a link --}}
            <div class="mt-6 -mb-2 space-y-2">
                <nav class="flex flex-wrap items-center gap-2" aria-label="Ciudad">
                    @foreach($filterChips['cities'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                </nav>
                <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Orden">
                    <span class="text-gray-500 dark:text-gray-400">Ordenar:</span>
                    @foreach($filterChips['sort'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                    <span class="filter-divider" aria-hidden="true"></span>
                    @foreach($filterChips['include_inactive'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                </nav>
            </div>

            <div class="mt-8 space-y-10">
                @forelse(collect($sections)->filter(fn ($section) => $section['houses']->isNotEmpty()) as $section)
                    <section>
                        <div class="mb-6">
                            <h2 @class([
                                'text-2xl font-bold',
                                'text-gray-900 dark:text-gray-100' => $loop->first,
                                'text-gray-500 dark:text-gray-400' => !$loop->first,
                            ])>
                                {{ $section['title'] }}
                                <span class="text-base font-medium text-gray-400">({{ $section['houses']->count() }})</span>
                            </h2>
                            @if($section['subtitle'])
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $section['subtitle'] }}</p>
                            @endif
                        </div>
                        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            @foreach($section['houses'] as $house)
                                @include('houses.card', ['house' => $house])
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="text-center py-8">
                        <p class="text-gray-600 dark:text-gray-400 text-lg font-medium">No hay casas disponibles en este momento.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
