{{-- resources/views/rentals/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Alquileres</h2>
    </x-slot>

    <div class="min-h-screen">
        {{-- Panel flotante de filtros --}}
        <div id="floatingFilter" class="fixed top-4 left-4 right-4 z-50 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 transform transition-all duration-300 ease-in-out -translate-y-full opacity-0">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filtros</h3>
                    <button id="closeFilter" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="GET" action="{{ route('rentals.index') }}" id="filterForm">
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Ubicación</h4>
                            
                            {{-- Campo de búsqueda --}}
                            <div class="relative mb-3">
                                <input type="text" 
                                       id="locationSearch"
                                       placeholder="Buscar ubicación..." 
                                       class="w-full px-3 py-2 pl-10 text-sm border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 max-h-48 overflow-y-auto" id="locationList">
                                @foreach($locationOptions as $value => $label)
                                    <label class="flex items-center location-option p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer" data-search="{{ strtolower($label) }}">
                                        <input type="checkbox" 
                                               name="locations[]" 
                                               value="{{ $value }}"
                                               {{ in_array($value, request('locations', [])) ? 'checked' : '' }}
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-900 dark:text-gray-100 truncate">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 mt-4">
                        <button type="submit" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                            Aplicar Filtros
                        </button>
                        <a href="{{ route('rentals.index') }}" 
                           class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-6 rounded-lg transition-colors duration-200 text-center inline-flex items-center justify-center">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Contenido principal --}}
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            {{-- Botón para abrir filtros --}}
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Alquileres en el Bolsón</h1>
                <button id="toggleFilter" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200 flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    <span>Filtros</span>
                </button>
            </div>
                <button id="enablePush" class="btn-primary mb-4">Activar notificaciones!</button>

                <button id="installBtn" hidden class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200 mb-2">Instalar app</button>
                <button id="iosHelpBtn" hidden class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200 mb-2">Instalar en iPhone</button>
                <div id="iosModal" hidden class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg p-6 shadow-lg max-w-md mx-auto mt-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Instrucciones para iPhone</h3>
                    <ol class="space-y-2 text-gray-800 dark:text-gray-200">
                        <li class="flex items-start">
                            <span class="bg-blue-100 text-blue-800 text-sm font-medium px-2 py-1 rounded-full mr-3 mt-0.5">1</span>
                            <span>Tocá <strong class="text-gray-900 dark:text-gray-100">Compartir</strong> (cuadrado con flecha).</span>
                        </li>
                        <li class="flex items-start">
                            <span class="bg-blue-100 text-blue-800 text-sm font-medium px-2 py-1 rounded-full mr-3 mt-0.5">2</span>
                            <span>Elegí <strong class="text-gray-900 dark:text-gray-100">Añadir a pantalla de inicio</strong>.</span>
                        </li>
                        <li class="flex items-start">
                            <span class="bg-blue-100 text-blue-800 text-sm font-medium px-2 py-1 rounded-full mr-3 mt-0.5">3</span>
                            <span>Confirmá con <strong class="text-gray-900 dark:text-gray-100">Añadir</strong>.</span>
                        </li>
                    </ol>
                </div>

                {{-- Incluir la vista de listado de alquileres --}}
                @include('rentals.list')
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Funcionalidad de búsqueda y panel flotante de filtros
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('locationSearch');
                const locationOptions = document.querySelectorAll('.location-option');
                const floatingFilter = document.getElementById('floatingFilter');
                const toggleFilterBtn = document.getElementById('toggleFilter');
                const closeFilterBtn = document.getElementById('closeFilter');
                
                // Funcionalidad de búsqueda
                if (searchInput && locationOptions.length > 0) {
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase().trim();
                        
                        locationOptions.forEach(function(option) {
                            const searchText = option.getAttribute('data-search');
                            
                            if (searchText.includes(searchTerm)) {
                                option.style.display = 'flex';
                            } else {
                                option.style.display = 'none';
                            }
                        });
                    });
                }
                
                // Funcionalidad de toggle del panel flotante
                function openFilter() {
                    floatingFilter.classList.remove('-translate-y-full', 'opacity-0');
                    floatingFilter.classList.add('translate-y-0', 'opacity-100');
                }
                
                function closeFilter() {
                    floatingFilter.classList.add('-translate-y-full', 'opacity-0');
                    floatingFilter.classList.remove('translate-y-0', 'opacity-100');
                }
                
                // Event listeners
                if (toggleFilterBtn) {
                    toggleFilterBtn.addEventListener('click', openFilter);
                }
                
                if (closeFilterBtn) {
                    closeFilterBtn.addEventListener('click', closeFilter);
                }
                
                // Cerrar panel con tecla Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && !floatingFilter.classList.contains('-translate-y-full')) {
                        closeFilter();
                    }
                });
                
                // Cerrar panel al hacer submit del formulario
                const filterForm = document.getElementById('filterForm');
                if (filterForm) {
                    filterForm.addEventListener('submit', function() {
                        setTimeout(closeFilter, 100); // Pequeño delay para que se vea el submit
                    });
                }
                
                // Cerrar panel al hacer click fuera de él
                document.addEventListener('click', function(e) {
                    if (!floatingFilter.contains(e.target) && !toggleFilterBtn.contains(e.target)) {
                        if (!floatingFilter.classList.contains('-translate-y-full')) {
                            closeFilter();
                        }
                    }
                });
            });
        </script>
        
        <script>
            console.log('test');
            window.VAPID_PUBLIC = @json(
        config('webpush.vapid.public_key')
        ?? config('services.vapid.public')
        ?? env('VAPID_PUBLIC_KEY')
      );
            console.log('VAPID_PUBLIC_KEY:', window.VAPID_PUBLIC);

            function urlBase64ToUint8Array(b64){
                const p='='.repeat((4-b64.length%4)%4);
                const base64=(b64+p).replace(/-/g,'+').replace(/_/g,'/');
                const raw=atob(base64);
                return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)));
            }

            document.getElementById('enablePush')?.addEventListener('click', async () => {
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) { alert('Push no soportado'); return; }
                const perm = await Notification.requestPermission();
                if (perm !== 'granted') { alert('Permiso denegado'); return; }

                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(window.VAPID_PUBLIC)
                });

                await fetch('/api/push/subscribe', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                    body: JSON.stringify(sub)
                });

                alert('Notificaciones activadas ✅');
            });
        </script>
        <script>
            let deferredPrompt = null;

            const isStandalone =
                window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true;

            const isIosSafari =
                /iphone|ipad|ipod/i.test(navigator.userAgent) &&
                /safari/i.test(navigator.userAgent) &&
                !/crios|fxios|edgios/i.test(navigator.userAgent);

            window.addEventListener('beforeinstallprompt', (e) => {
                console.log('> beforeinstallprompt')
                e.preventDefault();              // usamos nuestro propio botón
                deferredPrompt = e;
                if (!isStandalone && !isIosSafari) {
                    document.getElementById('installBtn').hidden = false;
                }
            });

            document.getElementById('installBtn').addEventListener('click', async () => {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                await deferredPrompt.userChoice; // 'accepted' | 'dismissed'
                deferredPrompt = null;
                document.getElementById('installBtn').hidden = true;
            });

            // iOS: no se puede abrir el prompt por código
            if (!isStandalone && isIosSafari) {
                document.getElementById('iosHelpBtn').hidden = false;
                document.getElementById('iosHelpBtn').onclick = () => {
                    document.getElementById('iosModal').hidden = false;
                };
            }

            window.addEventListener('appinstalled', () => {
                document.getElementById('installBtn').hidden = true;
            });
        </script>
    @endpush
</x-app-layout>
