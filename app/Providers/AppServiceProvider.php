<?php

namespace App\Providers;

use App\Contracts\IScraperService;
use App\Contracts\IWhatsappService;
use App\Services\KapsoService;
use App\Services\MockScraperServiceService;
use App\Services\ScraperServiceService;
use App\Services\TwilioService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $scrapeMock = config('mock.scrape_mock');

        $this->app->singleton(IScraperService::class, function ($app) use ($scrapeMock) {
            return match ($scrapeMock) {
                true => $app->make(MockScraperServiceService::class),
                false => $app->make(ScraperServiceService::class),
                default => throw new \InvalidArgumentException("Unknown scrape_mock value: $scrapeMock"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
