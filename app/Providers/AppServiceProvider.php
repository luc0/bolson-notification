<?php

namespace App\Providers;

use App\Domains\Harvesting\Contracts\RentalScraper;
use App\Domains\Harvesting\Services\BrowserRentalScraper;
use App\Domains\Harvesting\Services\FakeRentalScraper;
use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        // Local behind an HTTPS tunnel (ngrok, to test from a phone): Sail receives plain http,
        // so URLs would be generated as http and the browser would block assets and push.
        $isLocalHttpsTunnel = $this->app->isLocal() && str_starts_with((string) config('app.url'), 'https://');
        if ($isLocalHttpsTunnel) {
            URL::forceScheme('https');
        }

        // Stable aliases instead of class names in polymorphic columns (e.g. push_subscriptions.subscribable_type),
        // so moving or renaming a model doesn't break stored rows.
        Relation::morphMap([
            'subscriber' => Subscriber::class,
            'user' => User::class,
        ]);

        // Admins (ADMIN_PHONES) see everything: @can('admin') in views, can:admin on routes.
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Members don't know about Subscriptions: Subscriptions reacts to a member leaving.
        User::deleting(fn (User $user) => Subscriber::where('user_id', $user->id)->first()?->delete());
    }
}
