# CLAUDE.md

Laravel app that scrapes rental listings (El Bolsón / Lago Puelo), processes them with AI, stores them, and sends push notifications (PWA) to subscribers. Admins also get lands and houses for sale and monthly price charts.

## Rules
- Run commands through Sail (`./vendor/bin/sail ...`), except `rentals:analyze` from local (see below).
- Everything in English (code, params, names, comments) except user-facing text, which is in Spanish. Docs in `docs/` are in Spanish.
  - The locale is `es`: Breeze strings written in English (`__('Profile')`) are translated in `lang/es.json`, framework messages (validation, auth, passwords) in `lang/es/*.php`. New user-facing text is written directly in Spanish.
- Clean code:
  - Don't nest ifs or use `else` when avoidable; prefer early returns.
  - For non-trivial if conditions, extract a descriptively named boolean variable and use it in the if.
  - Follow SOLID: one responsibility per class (e.g. scraper, extractor, importer and classifier are separate), extend through interfaces instead of editing existing code (e.g. a new chart is a new `PriceSeries`, a new channel a new notification channel), depend on contracts injected by the container (e.g. `RentalScraper`), keep interfaces small. Don't add abstractions with a single implementation and no real need for one.
- Architecture (concepts from *Software Architecture: The Hard Parts*):
  - Trade-off analysis: there are no best practices, only trade-offs. For a non-obvious design decision (new domain, splitting or merging one, where data lives, sync vs async, a new external dependency), weigh the options and record it as an ADR (Architecture Decision Record) in the "Decisiones de arquitectura (ADR)" section of `docs/architecture.md`: context, decision, alternatives considered and consequences (what we gain and what we give up).
  - Data ownership: every table belongs to exactly one domain, and only that domain's models write to it. Other domains read through the owner's models or queries; Harvesting writes into Rentals, Lands and Houses only through its importers using their models. Analytics (Market) never writes the catalogs and keeps its own tables (`price_stats`, `exchange_rates`).
  - Fitness functions: the dependency rule is enforced by Pest arch tests in `tests/Arch/DependencyRuleTest.php`. Never weaken a test to make a change pass: fix the dependency, or record an ADR that changes the rule and update the test, `docs/architecture.md` and this file together. A new domain gets its rules there too.
- Keep the docs up to date in the same change as the code. When adding or changing a feature, update this file (only if a rule, command or gotcha changes) and the matching doc:
  - `docs/architecture.md` if a domain, class, flow, filter or command changes.
  - `docs/production.md` if env vars, server requirements, the deploy script or the schedule change. One-off deploy steps (data migrations, backfills) go to its "Próximo deploy" section.
  - `docs/history.md` once something is done in production (move the "Próximo deploy" steps there, with the date).
  - `docs/todo.md`: mark what's done and add what's left.
  - `docs/business/` for product or pricing decisions.

## Documentation (`docs/`)
- `docs/architecture.md` → domains (what each one solves, subdomains, classes, flows, filters, commands), dependency rule, cross-cutting pieces (PWA updates, locale) and the architecture decisions (ADRs). Read it before adding features.
- `docs/production.md` → how prod is built and operated: current state, server requirements, env vars, deploy script, scheduler, "Próximo deploy" (pending one-off steps), post-deploy checklist, known risks, Forge settings and why, rebuilding the server (Lightsail + Forge runbook), domain change, PHP upgrade.
- `docs/history.md` → what was already done in prod, with dates: server migration (Hetzner → Lightsail), one-off deploys, verifications.
- `docs/todo.md` → pending tasks by category (data quality, testing, security, features, sources, infra, code), each tagged with its milestone. Gitignored. Read it before proposing features or fixing AI parsing issues.
- `docs/business/strategy.md` → business and product decisions (plans, pricing, free vs subscribed, launch stages). Read it before touching subscriptions or access rules, and record new decisions there.
- `README.md` → short intro (what the app does, stack, architecture highlights) and local setup.

## Commands
- Start Sail and update rentals (`rentals:analyze` from local): `./scripts/local-up.sh`
- Switch `APP_URL` between localhost and an ngrok tunnel (to test from a phone): `./scripts/switch-url.sh ngrok` / `./scripts/switch-url.sh local`
- Tests (Pest): `./vendor/bin/sail test` (or locally: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/pest`)
- Run scraper from local (no Sail, DB in container): `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:analyze` (lands: `lands:analyze`, houses: `houses:analyze`)
- Complete detail page data: `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:fetch-details` (`--missing-titles` for AI titles)
- Reclassify city / neighborhood / property use without AI: `rentals:classify`, `lands:classify`, `houses:classify`
- Recalculate the price charts: `market:snapshot --backfill`
- Check DB connection: `php artisan db:test`
- Send a test Telegram message to a member (chat linked from the profile): `php artisan telegram:send-test <phone> "<message>"`
- Send a test web push to every browser of a member ("Activar notificaciones" tapped in the app): `php artisan push:send-test <phone> "<message>"` (reports each browser; expired ones are removed)
- Renew a subscription by hand: `subscriptions:extend <phone> [--days=]`
- If permission errors after using Sail:
  `rm -f bootstrap/cache/*.php && mkdir -p storage/logs && chmod -R 777 storage bootstrap/cache`

## Structure (modular monolith)
- Domains (details in `docs/architecture.md`): Sources, Harvesting, Locations, Rentals, Lands, Houses, Market, Subscriptions, Members, Payments (not created yet, comes with the checkout in v1.1).
- Each domain lives in `app/Domains/<Domain>/` using Laravel's folder names inside (`Models`, `Enums`, `Data`, `Support`, `Services`...).
- Framework entry points stay where Laravel expects them: `app/Http`, `app/Console`, `resources/views`, `database/migrations`.
- Shared external clients (Groq, Telegram, ArgentinaDatos, Puppeteer, HTTP fetch) go to `app/Integrations/`.
- Dependency rule: Locations (shared kernel: `City`, `CityResolver`) depends on nothing; Rentals, Lands and Houses depend only on Locations (never on each other); Harvesting writes into Rentals, Lands and Houses; Subscriptions reads from Rentals; Market reads from Rentals, Lands and Houses. Enforced by `tests/Arch/DependencyRuleTest.php`.
- Harvesting classes are named symmetrically per catalog: `BrowserRentalScraper` / `BrowserLandScraper` / `BrowserHouseScraper`, `…ListingExtractor`, `…Importer`, `…Classifier`.
- Models outside `App\Models` need `#[UseFactory(...)]` (and `$model` in the factory) because Laravel can't guess the factory name.
- Polymorphic models must be registered in `Relation::morphMap()` (`AppServiceProvider::boot()`) so stored types don't depend on class names.

## Gotchas
- `Rental`: `source` is the listing URL, stored decoded (`RentalLink`), not unique (a listing relisted after `relisting_after_days` is a new row); `site_path` is the source listing URL; `content` is the agency title, `title` the AI one (fallback to `content`); `source_path` is unused. Use `Rental::available()` for availability (`active` + no `rented_at`).
- Listing identity and prices (rentals, lands, houses): the importer matches by URL, then by `external_id` (agency id from the link, `listing_id_pattern` in `config/sources.php`) within the same source. The catalog row always has the latest confirmed price: a new price waits in `pending_price*` until the next read agrees, and the scraper doesn't save the snapshot of a source with pending prices so it's re-read. History lives in each domain's `*_price_changes` table (first row on create, then confirmed changes). Market uses the first known price (`firstKnownPrice()`), not the catalog's current one. Only listings active or deactivated less than `harvesting.relisting_after_days` ago (`deactivated_at`) are matched; later it's a new listing. Rules in ADR-003, ADR-004 and ADR-005.
- Sources: `config/sources.php` is the single source of truth (`rental_sites`, `land_sites`, `house_sites`); to add a site, add it there only. Long listings (30+ items) make the AI return a different subset each run: split big sources by city with `default_city` (like `disalvo_*`). Lands need `land_reasoning_effort` `medium` (with `low` the AI returns an empty list).
- City / neighborhood / property use are resolved on import by the `…Classifier`s. To recognize a new one: add it to `City::aliases()` / `City::neighborhoods()` / `PropertyUse::keywords()` and run the `*:classify` commands.
- Admin-only pages (Terrenos, Casas, Análisis): `admin` Gate backed by `ADMIN_PHONES`; `@can('admin')` in views, `can:admin` on routes.
- Market: to add a chart, implement `PriceSeries` and list it in `config/market.php`. After changing what a series measures or the `size_ranges`, run `market:snapshot --backfill` (and add it to "Próximo deploy" in `docs/production.md`).
- Subscriptions: only active subscribers (`Subscriber::active()`) get notifications; without one (admins excepted) the listing is delayed `free_delay_days`. Rules in `docs/business/strategy.md`.
- Push routes are web routes under `auth` (session + CSRF); there are no API routes.
- Telegram: local uses its own dev bot, never the prod one (`TechitoBot`). Run `telegram:set-webhook` whenever `APP_URL` changes (`switch-url.sh ngrok` does it).
- PWA: a deploy reaches the installed app on its own (`GET /version`); only manifest changes (name, icon) need a reinstall.

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

## Roadmap and prod
- Milestones: v0.9 private MVP → v0.95 fixes → v1.0 public beta (free) → v1.1 paid beta (Mercado Pago) → WhatsApp version.
- Prod: AWS Lightsail instance managed with Laravel Forge, MySQL 8.4, scheduler via `schedule:run` (all in `docs/production.md`).
- Local (Sail, MySQL 8.0) and prod (MySQL 8.4) use the same engine; prod was PostgreSQL until 2026-11, so keep migrations portable.
