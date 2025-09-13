{{-- resources/views/rentals/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Alquileres</h2>
    </x-slot>

    <h1 class="text-xl font-bold mb-4">Alquileres en el Bolsón</h1>
    <button id="enablePush">Activar notificaciones!</button>

    <button id="installBtn" hidden>Instalar app</button>
    <button id="iosHelpBtn" hidden>Instalar en iPhone</button>
    <div id="iosModal" hidden>
        <ol>
            <li>Tocá <b>Compartir</b> (cuadrado con flecha).</li>
            <li>Elegí <b>Añadir a pantalla de inicio</b>.</li>
            <li>Confirmá con <b>Añadir</b>.</li>
        </ol>
    </div>

    @push('scripts')
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
