<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Producción
Pasos de deploy, variables de entorno y consideraciones: [`docs/production.md`](docs/production.md).

# Dominios
La organización por dominios (qué resuelve cada uno y sus subdominios) está en [`docs/domains.md`](docs/domains.md).

Dockerfile: 8.4 -> Customized image: Added puppeteer to Sail Dockerfile.
- Google Chrome se instala solo en amd64 (no existe build linux/arm64). En Mac M1+ la imagen se buildea sin Chrome, por eso el scraper (`rentals:analyze`) se corre por fuera de sail.

# LLM reglas
Ver `CLAUDE.md`.

# Instalar PWA desde celu
para que aparezca el boton:
- usar ngrok, cambiarlo de todos lados (back y vite)
- desde el celu abrirlo desde chrome!

# Que env DB usar para cada caso
- activar notifications -> sail db
- usar app -> sail
- ejecutar comando -> sin sail `DB_HOST=127.0.0.1 DB_PORT=3307 php artisan rentals:analyze`

# Ejecutar comando rentals:analyze desde local
- levantar sail (por la db) pero correr cosas por fuera
- si falla, en env, usar la conexion sin container SIN SAIL.
- probar si la conexion a la db esta ok: `php artisan db:test`
- probablemente si use sail tengo que limpiar permisos y carpetas:
```
rm -f bootstrap/cache/*.php
mkdir -p storage/logs
chmod -R 777 storage bootstrap/cache
... correr comando
```
- correr `php artisan rentals:analyze`

# Rentals model
source -> es el sitio del rental en especifico (item)
site_path -> es donde scrapea (listado)
source_path -> no se usa habria que borrar

# Rentals filter
- por ahora hice que en el filtro se pueda filtrar por las location que aparecen en el enum + las de la db.

# Fuentes (sitios que se scrapean)
- Se definen solo en `config/sources.php`. Para agregar una inmobiliaria se agrega ahí y listo.
- `BrowserRentalScraper` le pasa los sitios a `scripts/scrape.cjs` (Puppeteer) por stdin.
- Capturas en `storage/app/captures`, snapshots (detección de cambios) en `storage/app/snapshots` (rutas en `config/harvesting.php`).
- Para forzar que se re-analice un sitio: borrar su snapshot.

# Detección de alquilados por foto
Algunas inmobiliarias (ej: Punto Patagonia) no despublican lo alquilado: le ponen un cartel "ALQUILADO"/"RESERVADO" a la foto.
- `scripts/scrape.cjs` reemplaza cada `<img src>` por un id corto (`#img-3`) y guarda las URLs reales en `storage/app/captures/<sitio>.images.json`. Descarta íconos (< 80px).
- El LLM devuelve el id de la foto principal en `Image`, y `BrowserRentalScraper` lo traduce a la URL.
- `RentalImageChecker` le pregunta a un modelo de visión (`GROQ_VISION_MODEL`, default `qwen/qwen3.6-27b`) si la foto tiene el cartel. Cada URL se revisa una sola vez (`image_checked_url`).
- Si da alquilado se setea `rented_at`: no se notifica y se muestra en "No disponibles". Si la revisión falla, el aviso queda disponible y se reintenta.

# Detalle de cada aviso (superficie, dormitorios, servicios...)
El listado no trae estos datos: están en la página de detalle de cada aviso.
- `RentalDetailService` (corre en `rentals:analyze`) baja el detalle por HTTP (`RentalDetailFetcher`, con reintentos: Tokko da 500 intermitentes) y lo extrae con el LLM (`RentalDetailExtractor`).
- Solo datos explícitos: cada valor se descarta si la página no tiene la palabra que lo respalda (ej: antigüedad sin "antigüedad"/"estrenar").
- Una vez por aviso disponible, hasta 3 intentos. Backfill manual: `php artisan rentals:fetch-details`.

# Modelos (Groq)
- `GROQ_MODEL` (default `openai/gpt-oss-20b`) y `GROQ_REASONING_EFFORT` (default `low`) para extraer avisos del HTML.
- Si Groq da de baja un modelo, el log muestra `model_not_found`: cambiar el env.

# TODO
- Desactivar un aviso recién cuando falta en N corridas seguidas (ver TODO en `RentalImporter::deactivateMissingRentals`). Hoy el LLM a veces saltea algunos avisos y quedan inactivos hasta la próxima corrida.
