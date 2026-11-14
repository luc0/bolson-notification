{{-- resources/views/rentals/index.blade.php --}}
<x-app-layout>
    <div class="min-h-screen">
        {{-- Contenido principal --}}
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-3">Alquileres en la Comarca Andina</h1>

                @if($freePlanDelayDays !== null)
                    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                        <p>Sin suscripción activa ves los alquileres publicados hace {{ $freePlanDelayDays }} días o más.</p>
                        @if($hiddenRecentCount > 0)
                            <p class="mt-1 font-medium">Hay {{ $hiddenRecentCount }} {{ $hiddenRecentCount === 1 ? 'alquiler nuevo' : 'alquileres nuevos' }} de los últimos {{ $freePlanDelayDays }} días que no estás viendo.</p>
                        @endif
                        <p class="mt-1">Pedinos renovar tu suscripción para verlos al instante y recibir los avisos.</p>
                    </div>
                @endif


                @if($canEnableNotifications)
                    <button id="enablePush" class="btn-primary mb-4">Activar notificaciones!</button>
                @endif

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

                {{-- One-click filters: each chip is a link --}}
                <div class="mt-6 -mb-2 space-y-2">
                    <nav class="flex flex-wrap items-center gap-2" aria-label="Ciudad">
                        @foreach($filterChips['cities'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                    </nav>
                    <details class="more-filters" @if($moreFiltersCount) open @endif>
                        <summary class="filter-chip cursor-pointer select-none">
                            Más filtros @if($moreFiltersCount) ({{ $moreFiltersCount }}) @endif
                        </summary>
                        <nav class="mt-2 flex flex-wrap items-center gap-2" aria-label="Tipo, precio, ambientes y servicios">
                            @foreach($filterChips['uses'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                            <span class="filter-divider" aria-hidden="true"></span>
                            @foreach($filterChips['price'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                            <span class="filter-divider" aria-hidden="true"></span>
                            @foreach($filterChips['rooms'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                            <span class="filter-divider" aria-hidden="true"></span>
                            @foreach($filterChips['gas'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                        </nav>
                    </details>
                    <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Orden">
                        <span class="text-gray-500 dark:text-gray-400">Ordenar:</span>
                        @foreach($filterChips['sort'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                        <span class="filter-divider" aria-hidden="true"></span>
                        @foreach($filterChips['include_inactive'] as $chip) <x-filter-chip :chip="$chip" /> @endforeach
                    </nav>
                </div>

                {{-- Incluir la vista de listado de alquileres --}}
                @include('rentals.list')
            </div>
        </div>
    </div>

    @push('scripts')
        
        <script>
            console.log('test');
            window.VAPID_PUBLIC = @json(config('webpush.vapid.public_key'));
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

                const response = await fetch(@json(route('push-subscriptions.store')), {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                    body: JSON.stringify(sub)
                });

                if (!response.ok) { alert('No se pudieron activar las notificaciones. Probá de nuevo.'); return; }
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
