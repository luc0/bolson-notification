<?php

namespace App\Providers;

use App\Contracts\IWhatsapp;
use App\Services\KapsoService;
use App\Services\TwilioService;
use Illuminate\Support\ServiceProvider;

class WhatsappServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $driver = config('whatsapp.driver', 'kapso');

        $this->app->singleton(IWhatsapp::class, function ($app) use ($driver) {
            return match ($driver) {
                'kapso' => $app->make(KapsoService::class),
                'twilio' => $app->make(TwilioService::class),
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
