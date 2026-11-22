{{-- resources/views/market/index.blade.php --}}
<x-app-layout>
    <div class="min-h-screen">
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-1">Análisis de precios</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Mediana de los avisos publicados cada mes; la banda es el 50% central de los precios.
                Los precios en otra moneda se convierten con el dólar {{ $dollar }} promedio del mes.
            </p>

            {{-- One-click filters: each chip is a link --}}
            <div class="mt-6 space-y-2">
                <nav class="flex flex-wrap items-center gap-2" aria-label="Ciudad">
                    @foreach($filterChips['cities'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                </nav>
                <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Moneda">
                    <span class="text-gray-500 dark:text-gray-400">Moneda:</span>
                    @foreach($filterChips['currency'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                </nav>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                @foreach($charts as $chart)
                    <section class="card p-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $chart['label'] }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ $chart['unit'] }} en {{ $chart['currency'] === 'USD' ? 'dólares' : 'pesos' }}</p>
                        @if(empty($chart['points']))
                            <p class="py-10 text-center text-gray-500 dark:text-gray-400">Todavía no hay datos.</p>
                        @else
                            <div class="relative h-64">
                                <canvas data-market-chart="{{ json_encode($chart) }}" aria-label="{{ $chart['label'] }}" role="img"></canvas>
                            </div>
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
