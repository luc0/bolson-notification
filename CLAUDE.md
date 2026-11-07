# CLAUDE.md

Laravel app that scrapes rental listings (El Bolsón / Lago Puelo), processes them with AI, stores them, and sends push notifications (PWA) to subscribers.

## Rules
- Run commands through Sail (`./vendor/bin/sail ...`), except `rentals:analyze` from local (see below).
- Everything in English (code, params, names, comments) except user-facing text, which is in Spanish.
- Clean code:
  - Don't nest ifs or use `else` when avoidable; prefer early returns.
  - For non-trivial if conditions, extract a descriptively named boolean variable and use it in the if.

## Commands
- Start Sail and update rentals (`rentals:analyze` from local): `./scripts/local-up.sh`
- Tests (Pest): `./vendor/bin/sail test` (or locally: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/pest`)
- Run scraper from local (no Sail, DB in container): `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:analyze` (lands: `lands:analyze`)
- Complete detail page data: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:fetch-details`
- Check DB connection: `php artisan db:test`
- If permission errors after using Sail:
  `rm -f bootstrap/cache/*.php && mkdir -p storage/logs && chmod -R 777 storage bootstrap/cache`

## Structure (modular monolith)
- Domains and their subdomains are documented in `docs/domains.md` (read it before adding features): Sources, Harvesting, Rentals, Lands, Subscriptions, Members, Payments.
- Each domain lives in `app/Domains/<Domain>/` using Laravel's folder names inside (`Models`, `Enums`, `Data`, `Support`, `Services`...).
- Framework entry points stay where Laravel expects them: `app/Http`, `app/Console`, `resources/views`, `database/migrations`.
- Shared external clients (Groq, Puppeteer, HTTP fetch) go to `app/Integrations/`.
- Dependency rule: Rentals and Lands depend on no other domain (and not on each other); Harvesting writes into Rentals and Lands; Subscriptions reads from Rentals.
- Migrated: Rentals, Lands, Sources, Harvesting (+ `app/Integrations/Groq`), Subscriptions, Members. Payments will be created with the checkout (v1.1).
- Models outside `App\Models` need `#[UseFactory(...)]` (and `$model` in the factory) because Laravel can't guess the factory name.
- Polymorphic models must be registered in `Relation::morphMap()` (`AppServiceProvider::boot()`) so stored types don't depend on class names.

## Domain
- `Rental`:
  - `source` → URL of the specific listing (item), stored decoded (`app/Domains/Rentals/Support/RentalLink.php`).
  - `site_path` → `listing_url` of the source it was scraped from.
  - `content` → the agency's title; `title` → short descriptive title written by the AI from the detail page (`RentalDetailExtractor`, shown on the card, falls back to `content`). Backfill: `rentals:fetch-details --missing-titles`.
  - `source_path` → unused, pending removal.
  - `price` (raw text) + `price_amount` / `price_currency` (parsed via `app/Domains/Rentals/Support/PriceParser.php`).
  - Availability: `active` (still listed on the site) + `rented_at` (photo stamped "ALQUILADO"). Use `Rental::available()`.
  - Old = detected 3+ months ago (`Rental::OLD_AFTER_MONTHS`).
  - Detail page data: `covered_area`, `total_area`, `bedrooms`, `parking_spaces`, `age_years`, `services` (json of `App\Domains\Rentals\Enums\Utility` values).
- Location: `location` is the raw text from the agency; `city` (`App\Domains\Rentals\Enums\City`) and `neighborhood` are resolved from it by `CityResolver` (aliases + known neighborhoods per city, evidence-based). Fallbacks: city from the detail page (AI, only if on the page), then the source `default_city`.
- Property use: `property_use` (`PropertyUse`: residential / commercial) resolved from the property type in the title by `PropertyUseResolver`; fallback: detail page (AI, only if the type is on the page).
- Both are set by `Harvesting\Rentals\Services\RentalClassifier` on import. Filters (one-click chips, `<x-filter-chip>`): city, and under "Más filtros" (`<details>`, opens when one is not default) property use (default: residential; `uses=all` for every use), price range (`PriceRange`: pesos ranges + dollars), rooms (4+ grouped), natural gas; include unavailable; sort (`RentalSort`: newest by default with no chip, price chips sort pesos, then dollars, then no price). Query in `Rentals\Queries\RentalSearch`.
- To recognize a new city/neighborhood/property type: add it to `City::aliases()` / `City::neighborhoods()` / `PropertyUse::keywords()` and run `rentals:classify` (no AI cost).

## Lands
- `Land` (`app/Domains/Lands`): lands for sale, separate from rentals (own table, `/terrenos` tab, `LandController`). Same `source` / `site_path` / `content` / price columns as `Rental`, plus `area` (m²). No notifications yet.
- Flow: `lands:analyze` → `Harvesting\Lands\Services\BrowserLandScraper` (capture in `storage/app/captures/lands`) → `LandListingExtractor` → `LandImporter`. Scheduled weekly in prod (Mondays 18:00), every 3 minutes in debug mode like rentals.
- The AI uses `services.groq.land_reasoning_effort` (default `medium`): with `low` it gives up on long land listings and returns an empty list.

## Sources
- `config/sources.php` is the single source of truth for scraped sites (key, type, listing URL, link base URL, `wait_for`). To add a site, add it there only.
- Read them with `App\Domains\Sources\Data\Source::rentals()` / `Source::findRental($key)` (`rental_sites`) and `Source::lands()` (`land_sites`); `SourceType` is `agency` or `portal`.
- `BrowserRentalScraper` passes the sites to `scripts/scrape.cjs` through stdin.
- Captures and snapshots are Harvesting config, per catalog: `config/harvesting.php` (`rentals.*`, `lands.*`), read with `CaptureStore::forRentals()` / `forLands()`.

## Architecture
- `app/Console/Commands/AnalyzeRentals.php` (`rentals:analyze`) → main flow: capture → AI extraction → store → detail enrichment → notify. Scheduled in `routes/console.php`.
- Harvesting (`app/Domains/Harvesting`): each catalog in its own folder (`Rentals/`, `Lands/`); shared pieces at the root (`Services/SourceCapturer`, `Services/AiListingExtractor`, `Support/CaptureStore`, `Data/Capture`, `Data/ImportResult`). Rental classes below live in `Harvesting/Rentals/`.
  - `Contracts/RentalScraper` → `BrowserRentalScraper` (Puppeteer) and `FakeRentalScraper` (`SCRAPE_MOCK=true`).
  - `Support/CaptureStore` + `Data/Capture` → captures and snapshots (change detection).
  - `RentalListingExtractor` → extracts rentals from a listing page capture.
  - `RentalImporter` → imports them into Rentals: create, reactivate, deactivate and rented photo check (`RentalImageChecker`).
  - `RentalDetailService` → detail page data (`RentalDetailFetcher` + `RentalDetailExtractor`). Backfill: `rentals:fetch-details`.
- `app/Integrations/Groq/GroqClient.php` → shared Groq API client (models in `config/services.php`).
- Subscriptions (`app/Domains/Subscriptions`):
  - `Subscriber` (one per member, `user_id`; legacy rows have it null) → `NewRentalsNotifier` sends web push to every subscriber with a push subscription.
  - Push subscribe/unsubscribe: `PushSubscriptionController`, web routes `push-subscriptions.store/destroy` under `auth` (needs session + CSRF). There are no API routes.
  - Deleting a member deletes its subscriber and push subscriptions (`User::deleting` listener in `AppServiceProvider`).
  - WhatsApp (`Contracts/WhatsappSender`: `TwilioWhatsappSender`, `KapsoWhatsappSender`) exists but is currently disabled.

## Laravel conventions
- Follow Laravel defaults before inventing structure; check how the framework itself names things.
- Naming: interfaces without `I` prefix (`RentalScraper`, like `Illuminate\Contracts\...`); no repeated suffixes (`BrowserRentalScraper`, not `ScraperServiceService`); test doubles prefixed `Fake` (like `Http::fake()`).
- Artisan commands: `group:action` (`rentals:analyze`, `rentals:fetch-details`); inject dependencies in `handle()`, not the constructor.
- Prefer framework facades/helpers that can be faked in tests: `Http`, `Process`, `File`, `Storage` (not `Symfony\Process`, `file_get_contents`, `curl`).
- Config over hardcoded values: read `config()` in code, `env()` only inside `config/*.php`. Config defaults must be safe for production.
- Generated files go in `storage/app/...`; non-PHP scripts in `scripts/`; nothing generated in the repo root.
- Routes: no debug/test endpoints outside `app()->isLocal()`.
- Code style: Laravel Pint (`vendor/bin/pint <paths>`), run it on the files you touch.
- Tests: Pest, `RefreshDatabase` is applied to `tests/Feature` in `tests/Pest.php`. Fake external calls (`Http::fake()`, `Process::fake()`).

## Roadmap
- See `todo.md` (in Spanish) for milestones, pending tasks and known data-extraction bugs. Read it before proposing features or fixing AI parsing issues.
- Milestones: v0.9 private MVP → v0.95 fixes → v1.0 public beta (free) → v1.1 paid beta (Mercado Pago) → WhatsApp version.
- Prod: Hetzner VPS managed with Laravel Forge, PostgreSQL, scheduler via `schedule:run` cron. Deploy steps, env vars and risks: `docs/production.md` (keep it updated when adding env vars, migrations with data changes or server requirements).
- Local dev uses MySQL but prod is PostgreSQL: check migrations that change column types or use raw SQL against Postgres too.
