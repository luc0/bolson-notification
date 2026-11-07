<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Avisos por Telegram</h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Te mandamos un mensaje cuando aparecen alquileres nuevos, además de la notificación del celu.
        </p>
    </header>

    <form method="post" action="{{ route('profile.telegram.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('patch')

        <label for="telegram_enabled" class="inline-flex items-center gap-2">
            <input type="hidden" name="telegram_enabled" value="0">
            <input id="telegram_enabled" name="telegram_enabled" type="checkbox" value="1" @checked($subscriber->telegram_enabled)
                   class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500">
            <span class="text-sm text-gray-700 dark:text-gray-300">Recibir avisos por Telegram</span>
        </label>

        @if($subscriber->hasTelegramLinked())
            <p class="text-sm text-emerald-700 dark:text-emerald-400">✓ Telegram conectado.</p>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Todavía no conectaste Telegram.
                <a href="{{ $telegramLink }}" target="_blank" rel="noopener noreferrer" class="font-medium text-indigo-600 dark:text-indigo-400 underline">Conectar Telegram</a>
                y tocá <strong>Iniciar</strong> en el chat del bot.
            </p>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'telegram-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                   class="text-sm text-gray-600 dark:text-gray-400">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
