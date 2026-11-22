# CLAUDE.md

Laravel app that scrapes rental listings (El Bolsón / Lago Puelo), processes them with AI, stores them, and sends push notifications (PWA) to subscribers.

## Rules
- Run commands through Sail (`./vendor/bin/sail ...`), except `rentals:analyze` from local (see below).
- Everything in English (code, params, names, comments) except user-facing text, which is in Spanish.
  - The locale is `es`: Breeze strings written in English (`__('Profile')`) are translated in `lang/es.json`, framework messages (validation, auth, passwords) in `lang/es/*.php`. New user-facing text is written directly in Spanish.
- Clean code:
  - Don't nest ifs or use `else` when avoidable; prefer early returns.
  - For non-trivial if conditions, extract a descriptively named boolean variable and use it in the if.

## Commands
- Start Sail and update rentals (`rentals:analyze` from local): `./scripts/local-up.sh`
- Switch `APP_URL` between localhost and an ngrok tunnel (to test from a phone): `./scripts/switch-url.sh ngrok` / `./scripts/switch-url.sh local`
- Tests (Pest): `./vendor/bin/sail test` (or locally: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/pest`)
- Run scraper from local (no Sail, DB in container): `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:analyze` (lands: `lands:analyze`)
- Complete detail page data: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:fetch-details`
- Check DB connection: `php artisan db:test`
- Send a test Telegram message to a member (chat linked from the profile): `php artisan telegram:send-test <phone> "<message>"`
- Send a test web push to every browser of a member ("Activar notificaciones" tapped in the app): `php artisan push:send-test <phone> "<message>"` (reports each browser; expired ones are removed)
- If permission errors after using Sail:
  `rm -f bootstrap/cache/*.php && mkdir -p storage/logs && chmod -R 777 storage bootstrap/cache`

## Structure (modular monolith)
- Domains and their subdomains are documented in `docs/domains.md` (read it before adding features): Sources, Harvesting, Locations, Rentals, Lands, Houses, Market, Subscriptions, Members, Payments.
- Each domain lives in `app/Domains/<Domain>/` using Laravel's folder names inside (`Models`, `Enums`, `Data`, `Support`, `Services`...).
- Framework entry points stay where Laravel expects them: `app/Http`, `app/Console`, `resources/views`, `database/migrations`.
- Shared external clients (Groq, Telegram, ArgentinaDatos, Puppeteer, HTTP fetch) go to `app/Integrations/`.
- Dependency rule: Locations (shared kernel: `City`, `CityResolver`) depends on nothing; Rentals, Lands and Houses depend only on Locations (never on each other); Harvesting writes into Rentals, Lands and Houses; Subscriptions reads from Rentals; Market reads from Rentals, Lands and Houses.
- Migrated: Locations, Rentals, Lands, Houses, Market, Sources, Harvesting (+ `app/Integrations/Groq`), Subscriptions, Members. Payments will be created with the checkout (v1.1).
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
- Location: `location` is the raw text from the agency; `city` (`App\Domains\Locations\Enums\City`) and `neighborhood` are resolved from it by `CityResolver` (aliases + known neighborhoods per city, evidence-based). Fallbacks: city from the detail page (AI, only if on the page), then the source `default_city`.
- Property use: `property_use` (`PropertyUse`: residential / commercial) resolved from the property type in the title by `PropertyUseResolver`; fallback: detail page (AI, only if the type is on the page).
- Both are set by `Harvesting\Services\RentalClassifier` on import. Filters (one-click chips, `<x-filter-chip>`): city, and under "Más filtros" (`<details>`, opens when one is not default) property use (default: residential; `uses=all` for every use), price range (`PriceRange`: pesos ranges + dollars), rooms (4+ grouped), natural gas; include unavailable; sort (`RentalSort`: newest by default with no chip, price chips sort pesos, then dollars, then no price). Query in `Rentals\Queries\RentalSearch`.
- To recognize a new city/neighborhood/property type: add it to `City::aliases()` / `City::neighborhoods()` / `PropertyUse::keywords()` and run `rentals:classify` / `lands:classify` (no AI cost).

## Lands
- `Land` (`app/Domains/Lands`): lands for sale, separate from rentals (own table, `/terrenos` tab, `LandController`). Admins only for now: `ADMIN_PHONES` (`config('app.admin_phones')`, `User::isAdmin()`) backs the `admin` Gate; the tab uses `@can('admin')` and the route `can:admin` (403 for other members). Same `source` / `site_path` / `content` / price columns as `Rental`, plus `area` (m²) and `city` / `neighborhood` (set by `Harvesting\Services\LandClassifier` on import, backfill with `lands:classify`). Filters: city chips, include unavailable, sort (`LandSort`). Query in `Lands\Queries\LandSearch`. No notifications yet.
- Flow: `lands:analyze` → `Harvesting\Services\BrowserLandScraper` (capture in `storage/app/captures/lands`) → `LandListingExtractor` → `LandImporter`. Scheduled weekly in prod (Mondays 18:00), every 3 minutes in debug mode like rentals.
- The AI uses `services.groq.land_reasoning_effort` (default `medium`): with `low` it gives up on long land listings and returns an empty list.

## Houses
- `House` (`app/Domains/Houses`): houses for sale, built like Lands (own table, `/casas` tab, `HouseController`, admins only with `@can('admin')` / `can:admin`). Same columns as `Land` but `bedrooms`, `bathrooms`, `covered_area` (built m²) and `total_area` (plot m²) instead of `area`. City / neighborhood by `Harvesting\Services\HouseClassifier` (backfill: `houses:classify`). Filters: city chips, include unavailable, sort (`HouseSort`). Query in `Houses\Queries\HouseSearch`. No notifications.
- Flow: `houses:analyze` → `BrowserHouseScraper` (capture in `storage/app/captures/houses`) → `HouseListingExtractor` → `HouseImporter`. Scheduled weekly in prod (Mondays 18:30). Sources in `config/sources.php` `house_sites` (`Source::houses()`).
- Long listings (30+ items) make the AI return a different subset on each run: split big sources by city (one source per city with `default_city`, like `disalvo_*`).

## Market (Análisis)
- `app/Domains/Market`: monthly price charts of the `/analisis` tab (`MarketController`, admins only). Reads Rentals, Lands and Houses; nothing depends on it.
- One class per chart implementing `Contracts/PriceSeries` (`Series/ResidentialRentals`, `CommercialRentals`, `HousesForSale`, `LandsForSale`), listed in `config/market.php`. To add a chart: new class + add it there; `MarketSnapshot` and the page pick it up.
- Measure: listings **published in each month** (`created_at`), so history can be rebuilt. Rentals in ARS and USD, houses in USD, lands in USD per m². Each listing is converted with the monthly average of `config('market.dollar')` (blue) before the median.
- Each chart has one median line per place (`PriceHistory::places()`: Toda la Comarca + every city with data, fixed color per place in `market-charts.js`); a picker above the charts (chips to remove, search box to add) applies to all of them and is kept in `?places=`. The p25–p75 band only shows with a single place.
- `market:snapshot` (1st of each month, previous month; `--month=YYYY-MM`, `--backfill`) syncs `exchange_rates` from `Integrations/ArgentinaDatos` and replaces that month's `price_stats` rows (per series, currency, city; `bedrooms` ready for houses via `groupsByBedrooms()`). Charts: Chart.js in `resources/js/market-charts.js`.

## Sources
- `config/sources.php` is the single source of truth for scraped sites (key, type, listing URL, link base URL, `wait_for`): `rental_sites`, `land_sites`, `house_sites`. To add a site, add it there only.
- Read them with `App\Domains\Sources\Data\Source::rentals()` / `Source::findRental($key)` (`rental_sites`) and `Source::lands()` (`land_sites`); `SourceType` is `agency` or `portal`.
- `BrowserRentalScraper` passes the sites to `scripts/scrape.cjs` through stdin. Paginated listings: `next_page_selector` (pages are merged into one capture so later pages aren't deactivated). Listing links in an `onclick` (no `href`) are kept as `href` by `scrape.cjs`.
- Captures and snapshots are Harvesting config, per catalog: `config/harvesting.php` (`rentals.*`, `lands.*`, `houses.*`), read with `CaptureStore::forRentals()` / `forLands()` / `forHouses()`.

## Architecture
- `app/Console/Commands/AnalyzeRentals.php` (`rentals:analyze`) → main flow: capture → AI extraction → store → detail enrichment → notify. Scheduled in `routes/console.php`.
- Harvesting (`app/Domains/Harvesting`): rentals, lands and houses share the same folders; classes are named symmetrically (`BrowserRentalScraper` / `BrowserLandScraper` / `BrowserHouseScraper`, `…ListingExtractor`, `…Importer`). Shared: `Services/SourceCapturer`, `Services/AiListingExtractor`, `Support/CaptureStore`, `Data/Capture`, `Data/ImportResult`.
  - `Contracts/RentalScraper` → `BrowserRentalScraper` (Puppeteer) and `FakeRentalScraper` (`SCRAPE_MOCK=true`).
  - `Support/CaptureStore` + `Data/Capture` → captures and snapshots (change detection).
  - `RentalListingExtractor` → extracts rentals from a listing page capture.
  - `RentalImporter` → imports them into Rentals: create, reactivate, deactivate and rented photo check (`RentalImageChecker`).
  - `RentalDetailService` → detail page data (`RentalDetailFetcher` + `RentalDetailExtractor`). Backfill: `rentals:fetch-details`.
- `app/Integrations/Groq/GroqClient.php` → shared Groq API client (models in `config/services.php`).
- Subscriptions (`app/Domains/Subscriptions`):
  - `Subscriber` (one per member, `user_id`; legacy rows have it null) → `NewRentalsNotifier` sends web push / Telegram to every **active** subscriber (`Subscriber::active()`, `expires_at` in the future).
  - Expiry (rules in `docs/business/strategy.md`, numbers in `config/subscriptions.php`): registering subscribes for one period (`Registered` listener in `AppServiceProvider`); `Subscriber::extend()` renews from the current expiry (or now if expired); manual renewal with `subscriptions:extend <phone> [--days=]`. Only active subscribers see the "Activar notificaciones" button. Without an active subscription (admins excepted) the rentals listing only shows rentals added `free_delay_days` ago or more (`RentalSearch::$addedBefore`, decided in `RentalController`).
  - `subscriptions:remind-expiring` (daily) → `ExpiryReminder`: once per period, only to members seen in the last `active_within_days` (`users.last_seen_at`, set by the `TrackLastSeen` web middleware at most once per hour).
  - Push subscribe/unsubscribe: `PushSubscriptionController`, web routes `push-subscriptions.store/destroy` under `auth` (needs session + CSRF). There are no API routes. Browser side in `resources/js/push.js` (VAPID key and URL from `<meta>` tags in `layouts/app.blade.php`): "Activar notificaciones" on the rentals page only shows when this browser is not subscribed (`pushManager.getSubscription()` with the current VAPID key; an existing one is re-sent so the server keeps it), and disabling lives in the profile ("Notificaciones en este dispositivo", only shown when subscribed).
  - Deleting a member deletes its subscriber and push subscriptions (`User::deleting` listener in `AppServiceProvider`).
  - Telegram: `Channels/TelegramChannel` (client in `app/Integrations/Telegram`). Members link their chat from the profile (`t.me/<bot>?start=<telegram_link_token>`, handled by `TelegramWebhookController` on `POST /telegram/webhook`, checked with `TELEGRAM_WEBHOOK_SECRET`) and toggle it with the "Recibir avisos por Telegram" checkbox (`subscribers.telegram_enabled`, default on). Admins register members from their linked chat with `/registrar <phone>` (other chats are ignored): `Members\Actions\RegisterMember` (shared with the web registration, fires `Registered` so it subscribes) with `members.initial_password`. Run `telegram:set-webhook` whenever `APP_URL` changes (`switch-url.sh ngrok` does it). Local uses its own dev bot, never the prod one (`TechitoBot`): a bot has a single webhook, so outside production the command refuses to replace a non-ngrok webhook (`--force` to override). Without `TELEGRAM_BOT_TOKEN` the channel and the profile section are off.
  - WhatsApp (`Contracts/WhatsappSender`: `TwilioWhatsappSender`, `KapsoWhatsappSender`) exists but is currently disabled.

## PWA updates
- The version (`App\Support\AppVersion`: `APP_VERSION` or the short hash of the deployed git commit) shows tiny in the bottom-right corner (`<x-app-version />`). When the installed app comes back to the foreground it polls `GET /version` and reloads if it changed (script in `layouts/app.blade.php`), so a deploy reaches it without reinstalling. Only manifest changes (name, icon) need a reinstall.

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
- Business and product decisions (plans, pricing, free vs subscribed, launch stages) live in `docs/business/` (in Spanish), starting with `docs/business/strategy.md`. Read it before touching subscriptions or access rules, and record new decisions there.
- Milestones: v0.9 private MVP → v0.95 fixes → v1.0 public beta (free) → v1.1 paid beta (Mercado Pago) → WhatsApp version.
- Prod: AWS Lightsail instance managed with Laravel Forge (Custom VPS, setup in `docs/lightsail.md`, Forge settings and why in `docs/forge.md`, domain change in `docs/domain-change.md`), MySQL 8.4, scheduler via `schedule:run` cron. Deploy steps, env vars and risks: `docs/production.md` (keep it updated when adding env vars, migrations with data changes or server requirements).
- Local (Sail, MySQL 8.0) and prod (MySQL 8.4) use the same engine; prod was PostgreSQL until 2026-11, so keep migrations portable.
