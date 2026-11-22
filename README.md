# Techito — Alquileres en la Comarca Andina

Techito junta en un solo lugar los alquileres que publican las inmobiliarias de El Bolsón, Lago Puelo y alrededores, y avisa por notificación cuando aparece uno nuevo. En la Comarca los alquileres duran poco y cada inmobiliaria publica en su propia web: la idea es enterarse antes, sin tener que revisar diez sitios todos los días.

Es un proyecto personal, en uso con un grupo chico de personas (beta privada).

## Qué hace

- **Recolecta avisos** de varias inmobiliarias: abre cada listado con un navegador headless y extrae los avisos con un LLM, porque cada sitio tiene un HTML distinto y no hay APIs.
- **Completa y limpia los datos**: precio y moneda normalizados, superficie, dormitorios, servicios, ciudad y barrio. Detecta los avisos que ya se alquilaron aunque la inmobiliaria no los despublique (un modelo de visión busca el cartel "ALQUILADO" en la foto).
- **Avisa** por web push (PWA instalable en el celular) y por un bot de Telegram.
- **Suscripciones** con vencimiento: con la suscripción activa los avisos llegan al momento; sin ella, el listado se ve con unos días de retraso.
- **Análisis de precios** (para admins): mediana mensual de alquileres, casas y terrenos por ciudad y por rango de superficie, convertida con el dólar del mes.

## Stack

Laravel 12 (PHP 8.4) · MySQL 8 · Blade + Alpine.js + Tailwind · Chart.js · Puppeteer (Node) · Groq (LLM y visión) · Web Push (VAPID) + service worker · Telegram Bot API · Pest · Docker (Laravel Sail) · AWS Lightsail + Laravel Forge.

## Arquitectura

- **Monolito modular por dominios** (`app/Domains/`): Sources, Harvesting, Locations, Rentals, Lands, Houses, Market, Subscriptions y Members. Cada uno sigue las convenciones de carpetas de Laravel, con una regla de dependencias explícita: los catálogos (alquileres, terrenos, casas) no se conocen entre sí, Harvesting escribe en ellos y Market solo los lee. Los clientes externos (Groq, Telegram, cotización del dólar) viven aparte, en `app/Integrations/`.
- **Pipeline de scraping con IA** (`rentals:analyze`): captura → detección de cambios → extracción → importación → enriquecimiento del detalle → notificación.
  - Se compara cada captura con la anterior para no gastar llamadas a la IA en sitios que no cambiaron.
  - La extracción usa un schema JSON estricto, con reintento y validación. Un dato del detalle solo se guarda si la página tiene la palabra que lo respalda, para que la IA no invente.
  - Las fotos se reemplazan por ids cortos antes de mandarle el HTML al modelo, lo que ahorra tokens y evita URLs mal copiadas.
- **Clasificación sin IA**: ciudad, barrio y tipo de propiedad salen de alias y barrios conocidos (basado en evidencia, nunca adivina), así se puede reclasificar toda la base sin costo.
- **Series de precios extensibles**: cada gráfico es una clase que implementa una interfaz (`PriceSeries`). Las estadísticas se calculan por mes de publicación, así la historia se puede reconstruir en cualquier momento.
- **Testeado con Pest** (~270 tests). Las llamadas externas (HTTP, procesos, IA) se reemplazan con fakes.
- **Producción** en una instancia de AWS Lightsail administrada con Laravel Forge: deploy desde `main` y scheduler por cron. La PWA instalada se actualiza sola después de cada deploy.

Más detalle en [`docs/`](docs): [`architecture.md`](docs/architecture.md) (dominios y flujos), [`production.md`](docs/production.md) (servidor y deploy) e [`history.md`](docs/history.md) (lo ya hecho en producción).

## Setup local

Requisitos: Docker, PHP 8.2+, Composer y Node 18+ (el scraper corre en la máquina local, ver abajo).

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan webpush:vapid      # claves para las notificaciones push
./vendor/bin/sail artisan migrate
npm install
npm run dev
```

La app queda en http://localhost. Para entrar, crear una cuenta en `/register`. Para ver Terrenos, Casas y Análisis, poner el teléfono en `ADMIN_PHONES` del `.env`.

Variables útiles del `.env`:
- `GROQ_API_KEY`: hace falta para extraer avisos con IA.
- `SCRAPE_MOCK=true`: usa datos de prueba en lugar de scrapear.
- `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` y `TELEGRAM_WEBHOOK_SECRET`: bot de Telegram. Usar un bot propio de desarrollo, nunca el de producción.

### Scraper (fuera de Sail)

La imagen de Sail no trae Chrome en Mac con Apple Silicon (Chrome no tiene build para linux/arm64), así que el scraper corre en la máquina local contra la base del contenedor (puerto 3307):

```bash
DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:analyze   # alquileres
DB_HOST=127.0.0.1 DB_PORT=3307 php artisan lands:analyze     # terrenos
DB_HOST=127.0.0.1 DB_PORT=3307 php artisan houses:analyze    # casas en venta
```

`./scripts/local-up.sh` levanta Sail y corre alquileres y terrenos. Para comprobar la conexión a la base: `php artisan db:test`.

Si después de usar Sail aparecen errores de permisos:

```bash
rm -f bootstrap/cache/*.php && mkdir -p storage/logs && chmod -R 777 storage bootstrap/cache
```

### Probar desde el celular (PWA y push)

Las notificaciones push y la instalación como PWA necesitan HTTPS:

```bash
./scripts/switch-url.sh ngrok   # APP_URL pasa a la URL https de ngrok (y registra el webhook de Telegram)
./scripts/switch-url.sh local   # vuelve a http://localhost
```

Abrir esa URL desde Chrome en el celular para que aparezca el botón de instalar.

### Tests

```bash
./vendor/bin/sail test
# o sin Sail:
DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/pest
```

Reglas de código y convenciones del proyecto: [`CLAUDE.md`](CLAUDE.md).
