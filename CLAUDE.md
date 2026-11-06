# CLAUDE.md

Laravel app that scrapes rental listings (El Bolsón / Lago Puelo), processes them with AI, stores them, and sends push notifications (PWA) to subscribers.

## Rules
- Run commands through Sail (`./vendor/bin/sail ...`), except `analyze:rentals` from local (see below).
- Everything in English (code, params, names, comments) except user-facing text, which is in Spanish.
- Clean code:
  - Don't nest ifs or use `else` when avoidable; prefer early returns.
  - For non-trivial if conditions, extract a descriptively named boolean variable and use it in the if.

## Commands
- Tests (Pest): `./vendor/bin/sail test`
- Run scraper from local (no Sail, DB in container): `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan analyze:rentals`
- Check DB connection: `php artisan db:test`
- If permission errors after using Sail:
  `rm -f bootstrap/cache/*.php && mkdir -p storage/logs && chmod -R 777 storage bootstrap/cache`

## Domain
- `Rental`:
  - `source` → URL of the specific listing (item).
  - `site_path` → URL of the listing page being scraped.
  - `source_path` → unused, pending removal.
  - `price` (raw text) + `price_amount` / `price_currency` (parsed via `app/Support/PriceParser.php`).
  - `active` → only active rentals are shown.
- `location`: free string. Filter options = `app/Location.php` enum values + distinct locations in DB.

## Architecture
- `app/Console/Commands/AnalyzeRentals.php` → main flow: scrape → AI processing → store → notify. Scheduled in `routes/console.php`.
- `app/Contracts/IScraperService.php` → real (`ScraperServiceService`, uses Puppeteer) and mock (`MockScraperServiceService`) implementations.
- `app/Services/AiProcessingService.php` → extracts structured data from scraped content.
- `app/Services/RentalService.php` → persistence.
- `app/Services/NotificationService.php` → web push to `AnonSubscriber`s.
- WhatsApp (`TwilioService`, `KapsoService`) exists but is currently disabled.

## Roadmap
- See `todo.md` (in Spanish) for milestones, pending tasks and known data-extraction bugs. Read it before proposing features or fixing AI parsing issues.
- Milestones: v0.9 private MVP → v0.95 fixes → v1.0 public beta (free) → v1.1 paid beta (Mercado Pago) → WhatsApp version.
- Prod: Hetzner VPS managed with Laravel Forge, PostgreSQL, scheduler via `schedule:run` cron.
