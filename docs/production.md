# Producción

Guía para desplegar y operar la app en producción. Infra actual: VPS en **Hetzner** administrado con **Laravel Forge**, base **PostgreSQL**, scheduler por cron.

> En local se desarrolla con MySQL (Sail) y en producción se usa PostgreSQL. Las migraciones y los tests se verificaron contra PostgreSQL 16 (ver [Verificaciones hechas](#verificaciones-hechas)). Si agregás migraciones con SQL a mano o `change()` de tipos, probalas también en Postgres.

## 1. Requisitos del servidor

| Requisito | Detalle |
|---|---|
| PHP | **8.2 o superior** (Laravel 12). Extensiones: `pdo_pgsql`, `curl`, `mbstring`, `openssl`, `json` (web push), `bcmath` o `gmp` (recomendado para el cifrado de web push). |
| PostgreSQL | Versión 14+ (verificado con 16). |
| Node.js + npm | Node 18+ (se usa Node 22 en local). Lo necesitan el build de assets **y el scraper** (`scripts/scrape.cjs` corre en cada `rentals:analyze`). |
| Chrome para Puppeteer | Puppeteer 24 descarga su propio Chrome con `npm ci`, en `~/.cache/puppeteer` del usuario que lo ejecuta (en Forge, `forge`). Necesita librerías del sistema: ver la lista en `docker/8.4/Dockerfile` (`libnss3`, `libgbm1`, `libgtk-3-0`, `libasound2t64`, `fonts-liberation`, etc.). Solo hay builds para **amd64**: el VPS tiene que ser x86_64. |
| HTTPS | **Obligatorio**: sin HTTPS no funcionan las notificaciones push ni la instalación como PWA. En Forge: pestaña SSL → Let's Encrypt. |

Para comprobar que Puppeteer funciona en el servidor, como el usuario `forge`:

```bash
cd /home/forge/<sitio> && php artisan rentals:analyze
tail -50 storage/logs/laravel.log   # debe decir "✅ Sources captured."
```

## 2. Variables de entorno

Las que usa la app además de las estándar de Laravel (`APP_*`, `DB_*`, `SESSION_*`, etc.):

| Variable | Valor en producción | Para qué |
|---|---|---|
| `APP_ENV` | `production` | Las rutas de debug de push solo existen con `local`. |
| `APP_DEBUG` | `false` | |
| `APP_URL` | `https://<dominio>` | Se usa en el link de las notificaciones push. |
| `APP_TIMEZONE` | `America/Argentina/Buenos_Aires` | **Importante**: el scraper corre "días hábiles a las 17:00" en esta zona. Sin esto son las 17:00 UTC (14:00 en Argentina). |
| `DB_CONNECTION` | `pgsql` | |
| `GROQ_API_KEY` | (secreto) | IA para extraer avisos, el detalle y las fotos. |
| `GROQ_MODEL` | `openai/gpt-oss-20b` (default) | Si Groq da de baja el modelo, el log muestra `model_not_found`: cambiarlo acá. |
| `GROQ_REASONING_EFFORT` | `low` (default) | |
| `GROQ_VISION_MODEL` | `qwen/qwen3.6-27b` (default) | Detección de carteles "ALQUILADO" en las fotos. |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT` | (secretos) | Web push. **No cambiarlas nunca**: si cambian, todas las suscripciones existentes dejan de funcionar y cada usuario tiene que volver a activar las notificaciones. Generarlas una sola vez con `php artisan webpush:vapid`. |
| `ANALYZE_RENTALS_DEBUG_MODE` | `false` o sin definir | Con `true` el scraper corre cada 3 minutos (gasta tokens). |
| `SCRAPE_MOCK` | `false` o sin definir | Con `true` usa `FakeRentalScraper` (datos de prueba). |
| `LOG_VIEWER_PHONES` | tu teléfono, ej. `+5492944xxxxxx` | Teléfonos (separados por coma) que pueden entrar a `/logs`. Vacío: nadie. |
| `LOG_CHANNEL` / `LOG_STACK` | `stack` / `daily` | Recomendado: un archivo por día, para que el log no crezca sin límite. |

WhatsApp (`WHATSAPP_DRIVER`, `TWILIO_*`, `KAPSO_API_KEY`) está apagado: no hace falta configurarlo.

## 3. Script de deploy (Forge)

```bash
cd /home/forge/<sitio>
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci           # también instala el Chrome de Puppeteer para el scraper
npm run build

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock
```

- Con `config:cache`, cualquier cambio en el `.env` necesita volver a correr `php artisan config:cache`.
- `npm ci` (no `npm ci --omit=dev`): Puppeteer está en `dependencies`, pero Vite y Tailwind están en `devDependencies` y hacen falta para `npm run build`.

## 4. Scheduler

En Forge → Scheduler, un cron cada minuto:

```
php /home/forge/<sitio>/artisan schedule:run
```

Corre `rentals:analyze` los días hábiles a las 17:00 (`routes/console.php`). Para ver qué está programado:

```bash
php artisan schedule:list
```

No hace falta un worker de colas: nada se encola hoy (ninguna clase implementa `ShouldQueue`), las notificaciones se envían dentro del mismo `rentals:analyze`.

## 5. Archivos que escribe la app

Todo dentro de `storage/` (tiene que ser escribible por el usuario de PHP y del cron, en Forge es `forge` para los dos):

| Ruta | Qué es |
|---|---|
| `storage/app/captures/` | Última captura HTML de cada fuente + `<fuente>.images.json`. |
| `storage/app/snapshots/` | Última captura procesada con éxito, para no reprocesar sitios sin cambios. Borrar un snapshot fuerza a re-analizar ese sitio. |
| `storage/app/rental.json` | Avisos nuevos de la última corrida (debug). |
| `storage/logs/` | Logs. |

## 6. Primer deploy de la rama `rentals-rented-detection`

Esta rama trae varias migraciones con cambios de datos. Pasos:

1. **Backup de la base** antes de deployar (Forge → Database → Backups, o `pg_dump`).
2. Configurar en el `.env` de producción: `APP_TIMEZONE`, `LOG_VIEWER_PHONES`, y revisar que `ANALYZE_RENTALS_DEBUG_MODE` y `SCRAPE_MOCK` no estén en `true`.
3. Deployar (el script corre `migrate --force`). Migraciones que se aplican:
   - `split_price_into_raw_amount_and_currency`: `price` pasa a texto; agrega `price_amount` y `price_currency`.
   - `add_image_and_rented_columns`: foto del aviso y `rented_at`.
   - `normalize_rental_sources`: decodifica los links y une los avisos duplicados (conserva la fecha de detección más vieja).
   - `fix_currency_of_legacy_rental_prices`: recalcula monto y moneda de los precios viejos a partir de la descripción.
   - `add_detail_columns`: superficie, dormitorios, cocheras, antigüedad, servicios.
   - `use_morph_alias_for_push_subscriptions` y `link_subscribers_to_users`: `anon_subscribers` pasa a `subscribers` con `user_id`; las suscripciones push existentes siguen funcionando.
   - `add_city_and_neighborhood` y `add_property_use`: columnas `city`, `neighborhood` y `property_use` (vacías hasta el paso 4).
4. Clasificar los avisos existentes (ciudad, barrio y uso; sin IA, instantáneo) y después completar el detalle (tarda unos minutos):
   ```bash
   php artisan rentals:classify
   php artisan rentals:fetch-details
   ```
5. Correr una vez el análisis completo y revisar el log:
   ```bash
   php artisan rentals:analyze
   ```
6. **Logs viejos**: antes de esta rama, entrar a `/logs` guardaba en el log el header `Authorization` (teléfono y contraseña en base64). Buscarlo y borrar esas líneas, y cambiar la contraseña de quien haya entrado:
   ```bash
   grep -l "Auth headers" storage/logs/*.log
   ```
7. El comando cambió de nombre: `analyze:rentals` → **`rentals:analyze`**. El cron usa `schedule:run`, así que no se rompe, pero si hay alguna tarea en Forge que llame al nombre viejo hay que actualizarla.
8. Las suscripciones push que ya existían quedan sin usuario (siguen recibiendo notificaciones). Cada persona queda ligada a su cuenta cuando vuelve a tocar "Activar notificaciones".

### Si algo sale mal

- Volver al commit anterior y restaurar el backup de la base es lo más seguro: algunas migraciones transforman datos (links, precios) y su `down()` no recupera el formato original exacto.
- Todas las migraciones tienen `down()` y se probaron en PostgreSQL, pero un `migrate:rollback` en producción es el último recurso.

## 7. Verificación después de cada deploy

- [ ] La home carga con HTTPS y se puede loguear.
- [ ] "Activar notificaciones" muestra "Notificaciones activadas ✅" (no el mensaje de error).
- [ ] `php artisan schedule:list` muestra `rentals:analyze` a las 17:00 de lunes a viernes.
- [ ] `php artisan rentals:analyze` termina sin `❌` en el log.
- [ ] `/logs` pide usuario y contraseña, y solo deja entrar a los teléfonos de `LOG_VIEWER_PHONES`.

## 8. Operación y riesgos conocidos

| Tema | Qué pasa | Qué hacer |
|---|---|---|
| Modelos de Groq | Groq da de baja modelos (ya pasó con `llama-4-scout`). Todos los sitios fallan con `model_not_found`. | Cambiar `GROQ_MODEL` / `GROQ_VISION_MODEL` y `config:cache`. Los sitios se reintentan solos en la próxima corrida. |
| Costo de IA | Cada sitio con cambios = 1 llamada; cada aviso nuevo = 1 llamada de detalle + 1 de foto. Del Agua cambia su HTML en cada corrida y se re-analiza siempre. | Ver consumo en console.groq.com (key que termina en la del `.env`). No dejar `ANALYZE_RENTALS_DEBUG_MODE=true`. |
| Sitios de Tokko | Punto Patagonia y Río Azul devuelven errores 500 intermitentes. | El fetch del detalle reintenta solo; si un aviso falla 3 veces queda sin detalle (`details_attempts`). Para reintentar: poner `details_attempts = 0`. |
| Avisos que aparecen y desaparecen | La IA a veces saltea algunos avisos de un listado largo: quedan inactivos hasta la corrida siguiente. | Pendiente en `todo.md`: desactivar recién tras N corridas. |
| Fallas por sitio | Si la IA o la importación falla, no se guarda el snapshot y el sitio se reintenta en la próxima corrida. | Nada; revisar el log si se repite. |
| VAPID | Cambiar las claves invalida todas las suscripciones. | No regenerarlas. |

## Verificaciones hechas

Contra PostgreSQL 16 (contenedor temporal):
- `migrate:fresh` completo y la suite de tests (110 tests) pasan.
- Rollback de todas las migraciones nuevas y vuelta a migrar.
- Upgrade con datos en el formato viejo de producción: avisos con precio numérico, links codificados duplicados y una suscripción push con `App\Models\AnonSubscriber`. Resultado: duplicados unidos, precios con su moneda, suscripción funcionando con el alias nuevo.
