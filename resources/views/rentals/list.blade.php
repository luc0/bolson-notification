{{-- resources/views/rentals/list.blade.php --}}
<div class="mt-8">
    <h2 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">Propiedades Disponibles</h2>
    
    @if(isset($rentals) && count($rentals) > 0)
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($rentals as $rental)
                <div class="card overflow-hidden">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="card-title">{{ $rental->content }}</h3>
                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">{{ $rental->source }}</span>
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
                            @if($rental->price)
                                <span class="text-xl font-bold text-green-600">${{ number_format($rental->price, 0, ',', '.') }}/mes</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8">
            <p class="text-gray-600 dark:text-gray-400 text-lg font-medium">No hay propiedades disponibles en este momento.</p>
        </div>
    @endif
</div>
