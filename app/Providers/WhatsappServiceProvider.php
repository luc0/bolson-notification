<?php

namespace App\Providers;

use App\Contracts\WhatsappSender;
use App\Services\KapsoWhatsappSender;
use App\Services\TwilioWhatsappSender;
use Illuminate\Support\ServiceProvider;

class WhatsappServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $driver = config('whatsapp.driver', 'kapso');

        $this->app->singleton(WhatsappSender::class, function ($app) use ($driver) {
            return match ($driver) {
                'kapso' => $app->make(KapsoWhatsappSender::class),
                'twilio' => $app->make(TwilioWhatsappSender::class),
                default => throw new \InvalidArgumentException("Unknown driver: $driver"),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
