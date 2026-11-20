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
            const VAPID_PUBLIC = @json(config('webpush.vapid.public_key'));

            function urlBase64ToUint8Array(b64) {
                const padding = '='.repeat((4 - b64.length % 4) % 4);
                const raw = atob((b64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
            }

            function sameKey(buffer, key) {
                const current = new Uint8Array(buffer ?? []);
                return current.length === key.length && current.every((byte, i) => byte === key[i]);
            }

            // Registers the service worker here instead of waiting on serviceWorker.ready,
            // which hangs forever (silently) when the registration on page load failed.
            async function activeServiceWorker() {
                const registration = await navigator.serviceWorker.register('/service-worker.js');
                const timeout = new Promise((_, reject) => setTimeout(() => reject(new Error('el service worker no se activó')), 10000));
                await Promise.race([navigator.serviceWorker.ready, timeout]);
                return registration;
            }

            // A subscription made with other VAPID keys makes subscribe() fail: drop it and subscribe again.
            async function pushSubscription(registration, key) {
                const existing = await registration.pushManager.getSubscription();
                const isReusable = existing && sameKey(existing.options?.applicationServerKey, key);
                if (isReusable) return existing;
                if (existing) await existing.unsubscribe();
                return registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
            }

            async function enablePush() {
                const isSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
                if (!isSupported) {
                    alert('Este navegador no permite notificaciones. En iPhone, primero instalá la app en la pantalla de inicio y abrila desde ahí.');
                    return;
                }
                if (!VAPID_PUBLIC) {
                    alert('Las notificaciones no están configuradas en el servidor.');
                    return;
                }

                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('No diste permiso para las notificaciones. Habilitalo en la configuración del navegador para este sitio.');
                    return;
                }

                const registration = await activeServiceWorker();
                const subscription = await pushSubscription(registration, urlBase64ToUint8Array(VAPID_PUBLIC));

                // Relative URL: an absolute one built from APP_URL breaks if the page was opened from another origin.
                const response = await fetch(@json(route('push-subscriptions.store', absolute: false)), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    body: JSON.stringify(subscription),
                });
                if (response.status === 419 || response.status === 401) {
                    alert('Tu sesión venció. Recargá la página y probá de nuevo.');
                    return;
                }
                if (!response.ok) {
                    alert(`No se pudieron activar las notificaciones (error ${response.status}). Probá de nuevo.`);
                    return;
                }

                alert('Notificaciones activadas ✅');
            }

            document.getElementById('enablePush')?.addEventListener('click', async (event) => {
                const button = event.currentTarget;
                button.disabled = true;
                try {
                    await enablePush();
                } catch (error) {
                    console.error(error);
                    alert(`No se pudieron activar las notificaciones: ${error.message || error}`);
                } finally {
                    button.disabled = false;
                }
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
