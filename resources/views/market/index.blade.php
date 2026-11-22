{{-- resources/views/market/index.blade.php --}}
<x-app-layout>
    <div class="min-h-screen">
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">Análisis de precios</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Mediana de los avisos publicados cada mes, por lugar. Con un solo lugar elegido, la banda es el 50% central de los precios. Casas y terrenos se pueden comparar por rango de superficie.
                Los precios en otra moneda se convierten con el dólar {{ $dollar }} promedio del mes.
            </p>

            <div class="mt-6 space-y-3">
                {{-- Places shown in every chart: chips to remove them + a search box to add them (market-charts.js) --}}
                <div data-place-picker data-places="{{ json_encode($places) }}" data-selected="{{ json_encode($selectedPlaces) }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <ul class="contents" data-place-chips aria-label="Lugares en los gráficos"></ul>
                        <div class="relative">
                            <input type="search" data-place-search autocomplete="off" placeholder="Agregar lugar…"
                                   role="combobox" aria-expanded="false" aria-controls="place-options" aria-label="Agregar lugar al gráfico"
                                   class="w-44 rounded-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 focus:border-blue-500 focus:ring-blue-500">
                            <ul id="place-options" data-place-options role="listbox" hidden
                                class="absolute z-10 mt-1 w-56 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg"></ul>
                        </div>
                    </div>
                </div>
                <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Moneda" data-keeps-places>
                    <span class="text-gray-500 dark:text-gray-400">Moneda:</span>
                    @foreach($currencyChips as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                </nav>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                @foreach($charts as $chart)
                    <section class="card p-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $chart['label'] }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $chart['unit'] }} en {{ $chart['currency'] === 'USD' ? 'dólares' : 'pesos' }}</p>
                        @if($chart['sizes'] && $chart['lines'])
                            {{-- Size range of this chart only (market-charts.js), kept in ?size[<series>]= --}}
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm" role="group" aria-label="{{ $chart['sizeLabel'] }}" data-size-picker="{{ $chart['key'] }}">
                                <span class="text-gray-500 dark:text-gray-400">{{ $chart['sizeLabel'] }}:</span>
                                <button type="button" class="filter-chip filter-chip-active" data-size="" aria-pressed="true">Todas</button>
                                @foreach($chart['sizes'] as $size)
                                    <button type="button" class="filter-chip" data-size="{{ $size['key'] }}" aria-pressed="false">{{ $size['label'] }}</button>
                                @endforeach
                            </div>
                        @endif
                        @if(empty($chart['lines']))
                            <p class="py-10 text-center text-gray-500 dark:text-gray-400">Todavía no hay datos.</p>
                        @else
                            <div class="relative mt-4 h-72">
                                <canvas data-market-chart="{{ json_encode($chart) }}" aria-label="{{ $chart['label'] }}" role="img"></canvas>
                            </div>
                            <p data-chart-empty hidden class="py-10 text-center text-gray-500 dark:text-gray-400">No hay datos de los lugares elegidos.</p>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/market-charts.js')
    @endpush
</x-app-layout>
