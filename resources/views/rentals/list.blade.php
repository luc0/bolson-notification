{{-- resources/views/rentals/list.blade.php --}}
@php
    $unavailableRentals = $rentals->reject(fn ($rental) => $rental->isAvailable());
    [$oldRentals, $recentRentals] = $rentals
        ->filter(fn ($rental) => $rental->isAvailable())
        ->partition(fn ($rental) => $rental->isOld());

    $sections = [
        [
            'title' => 'Propiedades Disponibles',
            'subtitle' => null,
            'rentals' => $recentRentals,
        ],
        [
            'title' => 'Antiguas',
            'subtitle' => 'Detectadas hace más de ' . \App\Domains\Rentals\Models\Rental::OLD_AFTER_MONTHS . ' meses. Siguen publicadas, pero probablemente ya no estén disponibles.',
            'rentals' => $oldRentals,
        ],
        [
            'title' => 'No disponibles',
            'subtitle' => 'Alquiladas (según el cartel en la foto) o que ya no aparecen en el sitio de la inmobiliaria.',
            'rentals' => $unavailableRentals,
        ],
    ];
@endphp

<div class="mt-8 space-y-10">
    @forelse(collect($sections)->filter(fn ($section) => $section['rentals']->isNotEmpty()) as $section)
        <section>
            <div class="mb-6">
                <h2 @class([
                    'text-2xl font-bold',
                    'text-gray-900 dark:text-gray-100' => $loop->first,
                    'text-gray-500 dark:text-gray-400' => !$loop->first,
                ])>
                    {{ $section['title'] }}
                    <span class="text-base font-medium text-gray-400">({{ $section['rentals']->count() }})</span>
                </h2>
                @if($section['subtitle'])
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $section['subtitle'] }}</p>
                @endif
            </div>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($section['rentals'] as $rental)
                    @include('rentals.card', ['rental' => $rental])
                @endforeach
            </div>
        </section>
    @empty
        <div class="text-center py-8">
            <p class="text-gray-600 dark:text-gray-400 text-lg font-medium">No hay propiedades disponibles en este momento.</p>
        </div>
    @endforelse
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Función para extraer el dominio root de una URL
    function getDomainRoot(url) {
        try {
            const urlObj = new URL(url);
            return urlObj.protocol + '//' + urlObj.hostname + '/';
        } catch (error) {
            console.error('Error parsing URL:', url, error);
            return url; // Retornar la URL original si hay error
        }
    }
    
    // Validar links de rentals al cargar la página
    const rentalLinks = document.querySelectorAll('.rental-link');
    
    rentalLinks.forEach(function(link) {
        const originalUrl = link.href;
        const sitePath = link.getAttribute('data-fallback-url');
        
        if (sitePath) {
            // Extraer solo el dominio root del site_path
            const fallbackUrl = getDomainRoot(sitePath);
            
            // Validar el link original
            fetch(originalUrl, { 
                method: 'HEAD', 
                mode: 'no-cors',
                cache: 'no-cache'
            }).then(function(response) {
                // Si hay respuesta, el link funciona
                console.log('✅ Link válido:', originalUrl);
            }).catch(function(error) {
                // Si hay error, usar el fallback (dominio root)
                console.log('🔗 Link roto, usando fallback:', originalUrl, '->', fallbackUrl);
                link.href = fallbackUrl;
                
                // Agregar indicador visual de que es un fallback
                link.style.opacity = '0.8';
                link.title = 'Link original no disponible, redirigiendo al sitio principal';
            });
        }
    });
    
    // También validar al hacer clic (como respaldo)
    rentalLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            const originalUrl = this.href;
            const sitePath = this.getAttribute('data-fallback-url');
            
            if (sitePath) {
                const fallbackUrl = getDomainRoot(sitePath);
                
                // Si el link actual es el fallback, no hacer validación adicional
                if (originalUrl === fallbackUrl) {
                    return;
                }
                
                // Intentar validar antes de navegar
                fetch(originalUrl, { 
                    method: 'HEAD', 
                    mode: 'no-cors',
                    cache: 'no-cache'
                }).catch(function(error) {
                    // Si falla, cambiar al fallback y navegar
                    e.preventDefault();
                    window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
                });
            }
        });
    });
});
</script>
