<?php

namespace App\Providers;

use App\Domains\Harvesting\Contracts\RentalScraper;
use App\Domains\Harvesting\Services\BrowserRentalScraper;
use App\Domains\Harvesting\Services\FakeRentalScraper;
use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\AnonSubscriber;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RentalScraper::class, function ($app) {
            $useFakeScraper = (bool) config('mock.scrape_mock');
            if ($useFakeScraper) {
                return $app->make(FakeRentalScraper::class);
            }

            return $app->make(BrowserRentalScraper::class);
        });
    }

    public function boot(): void
    {
        // Stable aliases instead of class names in polymorphic columns (e.g. push_subscriptions.subscribable_type),
        // so moving or renaming a model doesn't break stored rows.
        Relation::morphMap([
            'anon_subscriber' => AnonSubscriber::class,
            'user' => User::class,
        ]);
    }
}
