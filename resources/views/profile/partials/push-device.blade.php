{{-- Shown by resources/js/push.js only when this browser is subscribed to push notifications. --}}
<section id="pushDevice" hidden class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
    <div class="max-w-xl">
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Notificaciones en este dispositivo</h2>
        </header>

        <div data-push-enabled>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Este dispositivo recibe una notificación cuando aparecen alquileres nuevos.
            </p>
            <x-secondary-button class="mt-4">Desactivar en este dispositivo</x-secondary-button>
        </div>

        <p data-push-disabled hidden class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Notificaciones desactivadas. Podés volver a activarlas desde Alquileres.
        </p>
    </div>
</section>
