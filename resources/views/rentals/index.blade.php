{{-- resources/views/rentals/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Alquileres</h2>
    </x-slot>

    <div class="min-h-screen">
        {{-- Contenido principal --}}
        <div class="px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-3">Alquileres en la Comarca Andina</h1>


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

                {{-- One-click filters: each chip is a link --}}
                <nav class="flex flex-wrap items-center gap-2 mt-6 -mb-2" aria-label="Filtros">
                    @foreach([$filterChips['cities'], $filterChips['uses']] as $chips)
                        @foreach($chips as $chip)
                            <a href="{{ $chip['url'] }}" @class(['filter-chip', 'filter-chip-active' => $chip['active']]) @if($chip['active']) aria-current="true" @endif>{{ $chip['label'] }}</a>
                        @endforeach
                        <span class="mx-1 h-5 w-px bg-gray-300 dark:bg-gray-600" aria-hidden="true"></span>
                    @endforeach
                    <a href="{{ $filterChips['inactive']['url'] }}" @class(['filter-chip', 'filter-chip-active' => $filterChips['inactive']['active']]) @if($filterChips['inactive']['active']) aria-current="true" @endif>{{ $filterChips['inactive']['active'] ? '✓ ' : '' }}{{ $filterChips['inactive']['label'] }}</a>
                </nav>

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
