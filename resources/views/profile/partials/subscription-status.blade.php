<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Tu suscripción</h2>
    </header>

    @if($subscriber->isActive())
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Activa hasta el <strong>{{ $subscriber->expires_at->format('d/m/Y') }}</strong>.
            Recibís los avisos de alquileres nuevos y ves todos los alquileres al instante.
        </p>
    @else
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            @if($subscriber->expires_at)
                Venció el <strong>{{ $subscriber->expires_at->format('d/m/Y') }}</strong>.
            @else
                No tenés una suscripción activa.
            @endif
            Sin suscripción no recibís avisos y ves los alquileres publicados hace {{ config('subscriptions.free_delay_days') }} días o más.
            Pedinos renovarla para volver a tener todo.
        </p>
    @endif
</section>
