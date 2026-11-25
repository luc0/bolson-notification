# Producción

Cómo está armada producción hoy, cómo se deploya y cómo se opera. Lo que ya se hizo en el servidor (migraciones, deploys puntuales, verificaciones) está en [`docs/history.md`](history.md).

## Estado actual

- **Servidor:** instancia `Techito-Prod` de **AWS Lightsail** (Ubuntu 24.04, 2 GB x86, snapshots automáticos de madrugada en Argentina, IP estática `Techito-Prod-IP`), conectada a **Laravel Forge** como Custom VPS (servidor `techito-prod`).
- **Sitio:** `techito.on-forge.com`, dominio gratuito de Forge y provisorio hasta comprar uno propio (sección 11). HTTPS con Let's Encrypt.
- **Base:** MySQL 8.4 LTS, base `techito` con usuario propio `techito`.
- **Scheduler:** Laravel Scheduler de Forge (`schedule:run` cada minuto).
- **Telegram:** prod usa el bot `TechitoBot`; local usa su propio bot.
- **Pendiente:** backups de la base (Forge → Database Backups a S3, o `mysqldump` diario por cron; el snapshot de disco con MySQL corriendo no es 100% confiable) y dominio definitivo.

> Local (Sail) y producción usan MySQL 8. Hasta noviembre de 2026 producción usaba PostgreSQL: las migraciones tienen que seguir siendo portables.

## 1. Requisitos del servidor

| Requisito | Detalle |
|---|---|
| PHP | **8.2 o superior** (Laravel 12). Extensiones: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `json` (web push), `bcmath` o `gmp` (recomendado para el cifrado de web push). |
| MySQL | 8.0+ (local 8.0, producción 8.4 LTS). |
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
| `APP_NAME` | `Techito` | Nombre en el título de la pestaña y al instalar en iPhone (el de Android sale de `public/manifest.webmanifest`). Cambiarlo cambia el nombre de la cookie de sesión: todos tienen que volver a loguearse una vez. |
| `APP_ENV` | `production` | Las rutas de debug de push solo existen con `local`. |
| `APP_DEBUG` | `false` | |
| `APP_URL` | `https://<dominio>` | Se usa en el link de las notificaciones push. |
| `APP_VERSION` | sin definir | Versión que se ve abajo a la derecha ("v…"). Sin definir es el hash corto del commit deployado (se lee de `.git`), así que cambia sola en cada deploy y la app instalada se recarga sola al volver a abrirla (`GET /version`). |
| `APP_LOCALE` | `es` o sin definir | Idioma de los textos del framework y de Breeze (`lang/es.json`, `lang/es/*.php`). Si el `.env` de Forge quedó con `APP_LOCALE=en` (viene así del `.env.example` de Laravel), esos textos se ven en inglés. |
| `APP_TIMEZONE` | `America/Argentina/Buenos_Aires` | **Importante**: el scraper corre "días hábiles a las 17:00" en esta zona. Sin esto son las 17:00 UTC (14:00 en Argentina). |
| `DB_CONNECTION` | `mysql` | `DB_HOST=127.0.0.1`, `DB_PORT=3306`, base y usuario creados en Forge → Database. |
| `GROQ_API_KEY` | (secreto) | IA para extraer avisos, el detalle y las fotos. |
| `GROQ_MODEL` | `openai/gpt-oss-20b` (default) | Si Groq da de baja el modelo, el log muestra `model_not_found`: cambiarlo acá. |
| `GROQ_REASONING_EFFORT` | `low` (default) | |
| `GROQ_LAND_REASONING_EFFORT` | `medium` (default) | Extracción de terrenos. Con `low` el modelo se rinde con listados largos y devuelve una lista vacía. |
| `GROQ_HOUSE_REASONING_EFFORT` | `medium` (default) | Extracción de casas en venta. Con `high` falla siempre (se queda sin tokens). |
| `GROQ_VISION_MODEL` | `qwen/qwen3.6-27b` (default) | Detección de carteles "ALQUILADO" en las fotos. |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_BOT_USERNAME` | (secreto) / nombre del bot sin @ | Avisos por Telegram (bot creado con @BotFather). Sin token, el canal y la sección del perfil quedan apagados. Prod usa `TechitoBot`; en local se usa **otro bot** (un bot tiene un solo webhook: si local registra el suyo, prod deja de recibir mensajes). Desde local, `telegram:set-webhook` se niega a pisar un webhook que no sea de ngrok. |
| `TELEGRAM_WEBHOOK_SECRET` | (secreto, un string largo al azar) | Telegram lo manda en cada llamada al webhook; sin él, `/telegram/webhook` responde 403. Después de configurarlo (o de cambiar `APP_URL`) correr `php artisan telegram:set-webhook`. |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT` | (secretos) | Web push. **No cambiarlas nunca**: si cambian, todas las suscripciones existentes dejan de funcionar y cada usuario tiene que volver a activar las notificaciones. Generarlas una sola vez con `php artisan webpush:vapid`. |
| `ANALYZE_RENTALS_DEBUG_MODE` | `false` o sin definir | Con `true` el scraper corre cada 3 minutos (gasta tokens). |
| `SCRAPE_MOCK` | `false` o sin definir | Con `true` usa `FakeRentalScraper` (datos de prueba). |
| `ADMIN_PHONES` | tu teléfono, tal cual se registró | Teléfonos (separados por coma) de los admins: ven todo, por ejemplo los tabs Terrenos, Casas y Análisis. Vacío: nadie. |
| `SUBSCRIPTION_DURATION_DAYS` | `30` o sin definir | Días que dura una suscripción (al registrarse y en cada renovación). |
| `MARKET_DOLLAR` | `blue` o sin definir | Dólar con el que la sección Análisis convierte precios (tipos de argentinadatos.com: `blue`, `oficial`, `bolsa`...). Si se cambia, correr `php artisan market:snapshot --backfill`. |
| `FREE_PLAN_DELAY_DAYS` | `3` o sin definir | Sin suscripción activa solo se ven los alquileres agregados hace esta cantidad de días o más. |
| `EXPIRY_REMINDER_DAYS_BEFORE` | `3` o sin definir | Cuántos días antes de vencer se avisa (Telegram / push). |
| `EXPIRY_REMINDER_ACTIVE_WITHIN_DAYS` | `5` o sin definir | Solo se avisa a quien abrió la app en los últimos N días. |
| `MEMBER_INITIAL_PASSWORD` | `12345` o sin definir | Clave de los usuarios que registra un admin desde el bot de Telegram (`/registrar <celular>`). |
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

Corre `rentals:analyze` los días hábiles a las 17:00, `lands:analyze` (terrenos, sin notificaciones) una vez por semana, los lunes a las 18:00, `houses:analyze` (casas en venta, sin notificaciones) los lunes a las 18:30, `subscriptions:remind-expiring` (aviso de vencimiento de la suscripción) todos los días a las 10:00 y `market:snapshot` (precios del mes pasado y del mes en curso, sección Análisis) los martes a las 04:00 (`routes/console.php`). Para ver qué está programado:

```bash
php artisan schedule:list
```

No hace falta un worker de colas: nada se encola hoy (ninguna clase implementa `ShouldQueue`), las notificaciones se envían dentro del mismo `rentals:analyze`.

## 5. Archivos que escribe la app

Todo dentro de `storage/` (tiene que ser escribible por el usuario de PHP y del cron, en Forge es `forge` para los dos):

| Ruta | Qué es |
|---|---|
| `storage/app/captures/` | Última captura HTML de cada fuente + `<fuente>.images.json`. Los terrenos en `captures/lands/` y las casas en `captures/houses/`. |
| `storage/app/snapshots/` | Última captura procesada con éxito, para no reprocesar sitios sin cambios. Borrar un snapshot fuerza a re-analizar ese sitio. Los terrenos en `snapshots/lands/` y las casas en `snapshots/houses/`. |
| `storage/app/rental.json` | Avisos nuevos de la última corrida (debug). |
| `storage/logs/` | Logs. |

## 6. Próximo deploy

Pasos que se corren **una sola vez** en el próximo deploy. Después de aplicarlos, moverlos a `docs/history.md` con la fecha.

### Sección Análisis y casas por m² cubierto

Migraciones nuevas (`2026_11_22_000001/2/3`): `exchange_rates` y `price_stats` (con `size_range`, rangos de superficie de casas y terrenos), vacías (sin cambios de datos).

Después del deploy, una sola vez, para calcular toda la historia (descarga el dólar de api.argentinadatos.com):

```bash
php artisan market:snapshot --backfill
```

Si ya se había corrido antes de los rangos de superficie o del precio por m² cubierto de las casas, volver a correr `--backfill`: reemplaza todos los meses (lo mismo si se cambian los cortes en `config/market.php` `size_ranges`).

Después, `php artisan schedule:list` tiene que mostrar `market:snapshot` los martes a las 04:00.

### Historial de precios e id de la inmobiliaria

Migraciones nuevas (`2026_11_24_000000` a `000005`), con cambios de datos que corren solas en `migrate`:
- `rental_price_changes`, `land_price_changes`, `house_price_changes`: una fila inicial (`created`) por cada aviso existente con su precio actual.
- `external_id` en `rentals`, `lands` y `houses`: se completa desde la URL con `listing_id_pattern` de `config/sources.php`.
- `pending_price`, `pending_price_amount`, `pending_price_currency` en `rentals`, `lands` y `houses` (vacías): precio nuevo esperando la segunda lectura.
- `deactivated_at` en `rentals`, `lands` y `houses`: los inactivos toman su `updated_at`. `source` deja de ser `unique` (pasa a índice común). El `down` falla si ya hay URLs repetidas.

Después del deploy, una sola vez (Market pasa a usar el primer precio del historial; hoy da lo mismo que antes, pero deja la base al día):

```bash
php artisan market:snapshot --backfill
```

Verificar: `php artisan tinker --execute="echo App\\Domains\\Rentals\\Models\\RentalPriceChange::count();"` igual a la cantidad de alquileres, y en el log de la próxima corrida, líneas `🔗 ... recognized by external_id`, `⏳ ... to confirm` (y en la corrida siguiente la misma fuente se vuelve a mandar a la IA aunque no haya cambiado) o `⚠️ Price change ignored` si las hubo.

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
| Costo de IA | Cada sitio con cambios = 1 llamada; cada aviso nuevo = 1 llamada de detalle (incluye el título) + 1 de foto. Del Agua cambia su HTML en cada corrida y se re-analiza siempre. | Ver consumo en console.groq.com (key que termina en la del `.env`). No dejar `ANALYZE_RENTALS_DEBUG_MODE=true`. |
| Sitios de Tokko | Punto Patagonia y Río Azul devuelven errores 500 intermitentes. | El fetch del detalle reintenta solo; si un aviso falla 3 veces queda sin detalle (`details_attempts`). Para reintentar: poner `details_attempts = 0`. |
| Avisos que aparecen y desaparecen | La IA a veces saltea algunos avisos de un listado largo: quedan inactivos hasta la corrida siguiente. | Pendiente en `docs/todo.md`: desactivar recién tras N corridas. |
| Fallas por sitio | Si la IA o la importación falla, no se guarda el snapshot y el sitio se reintenta en la próxima corrida. | Nada; revisar el log si se repite. |
| VAPID | Cambiar las claves invalida todas las suscripciones. | No regenerarlas. |
| Cotización del dólar | `market:snapshot` la baja de api.argentinadatos.com (pública, sin clave). Si no responde, usa las cotizaciones guardadas: el mes sin cotización queda sin los avisos en otra moneda. | Volver a correr `php artisan market:snapshot --month=YYYY-MM` cuando la API vuelva. |
| Suscripciones | Vencen (`subscribers.expires_at`): sin suscripción activa no hay avisos y el listado se ve con retraso. La renovación es manual. | Renovar: `php artisan subscriptions:extend <teléfono>` (suma `SUBSCRIPTION_DURATION_DAYS`, o `--days=N`). |

## 9. Forge: configuración y por qué

### Servidor (`techito-prod`)

- Proveedor **Custom VPS** (Forge no se integra con Lightsail). App Server, Ubuntu 24.04, PHP 8.4, MySQL 8.4 LTS.
- **IP address = IP estática pública** de Lightsail; **Private IP = la `172.26.x.x`**. Si se cruzan, el script de aprovisionamiento termina con `ssh: connect to host 172.26.x.x port 22: Connection timed out` y hay que borrar el servidor en Forge y crearlo de nuevo.
- El comando `wget ... forge.sh; bash forge.sh` se corre **en el servidor** (consola SSH de Lightsail, después de `sudo -i`), no en la Mac. Mientras Forge dice *Waiting on your server to become ready* está esperando justamente eso.
- Durante el aprovisionamiento pueden aparecer los carteles *Pending kernel upgrade* / *Which services should be restarted?*: Enter y sigue. Reiniciar el servidor recién cuando Forge lo marque *Active*.
- **Contraseña sudo** del usuario `forge`: Forge la muestra una sola vez al crear el servidor. Si se pierde: `sudo passwd forge` desde la consola de Lightsail (el usuario `ubuntu` tiene sudo sin contraseña).
- **SSH**: se entra como `forge` con la clave agregada en servidor → SSH Keys (hoy `~/.ssh/id_rsa.pub` de la Mac).

### Recipes

- La de Puppeteer (librerías de Chrome + swap) se corre **como `root`** (usa `apt-get` y crea `/swapfile2`). Contenido en [Recrear el servidor](#10-recrear-el-servidor-lightsail--forge), paso 3.
- **No se usa como post-provision recipe**: se corre a mano después de que el servidor queda *Active*, así si falla se sabe que es la recipe y no el aprovisionamiento.
- Forge ya crea un swap de 1 GB (`/swapfile`); se agregó otro de 1 GB (`/swapfile2`) para llegar a 2 GB.

### Sitio

- Tipo **Laravel**, web directory `/public`, PHP 8.4.
- Dominio: `techito.on-forge.com`, el gratuito de Forge, mientras no haya uno propio. Mejor que `sslip.io`: no depende de un DNS de terceros ni choca con los límites de Let's Encrypt de un dominio compartido.
- Advanced settings: defaults, salvo:
  - **Zero-downtime deployments: desactivado.** Con zero-downtime cada deploy crea una carpeta de release nueva y corre `npm ci` desde cero, lo que vuelve a descargar el Chrome de Puppeteer (~150 MB) y deja un `node_modules` completo por release en un disco chico. Además el script de deploy de la sección 3 está escrito para un deploy en el lugar (`cd /home/forge/<sitio>` + `git pull`). El costo es unos segundos de corte durante cada deploy, aceptable para esta app.
  - **User isolation: desactivado.** El sitio corre como `forge`: las rutas de los docs (`/home/forge/<sitio>`) y el Chrome de Puppeteer (en `~/.cache/puppeteer` de `forge`) dependen de eso.
- Octane, Horizon, queue workers y Reverb: no (la app no encola nada).
- Laravel Scheduler: activado (equivale al cron `schedule:run` cada minuto de la sección 4).
- Si al crear el sitio Forge dice *Forge was unable to create the environment file*: revisar por SSH que la carpeta del sitio tenga el código y volver a guardar el Environment.
- No deployar antes de cargar el **Environment**: sin los `DB_*` correctos el `migrate --force` del deploy falla.

### Base de datos

- Base `techito`, usuario propio `techito` (no el `forge` por defecto). Arranca vacía.

## 10. Recrear el servidor (Lightsail + Forge)

Paso a paso para armar el servidor de cero (así se hizo en noviembre de 2026, ver `docs/history.md`). Forge no tiene integración con Lightsail: se conecta la instancia como **Custom VPS** y Forge la aprovisiona (nginx, PHP, MySQL, Composer, Node, firewall, usuario `forge`).

> Si ya hay prod andando en otro servidor: copiar de ese `.env` las claves `VAPID_*` (si se regeneran, todas las suscripciones push dejan de funcionar).

### Paso 1 — Instancia

- Instancia `Techito-Prod`: Ubuntu 24.04, General purpose 2 GB x86, con snapshots automáticos (de madrugada en Argentina).
- IP estática `Techito-Prod-IP` asociada.
- Firewall de Lightsail: 22, 80 y 443 abiertos a cualquier IPv4 e IPv6 (Forge necesita el 22 para conectarse; además configura `ufw` adentro).
- Sin dominio todavía: se usa el dominio gratuito de Forge (`*.on-forge.com`) como provisorio (mejor que `sslip.io`: no depende de un DNS de terceros ni de los límites de Let's Encrypt). Al pasar al definitivo: sección 11.

> Forge necesita un servidor **recién creado**. Si ya se instalaron paquetes a mano (nginx, PHP, MySQL), crear una instancia nueva, pasarle la IP estática y borrar la vieja.

### Paso 2 — Conectar el servidor a Forge

0. Antes: en Lightsail → Connect using SSH, `which nginx php mysql` no tiene que devolver nada. Anotar la IP estática y la privada (`172.26.x.x`, pestaña *Networking*).
1. Forge → **Create Server** → proveedor **Custom VPS**.
2. Completar:
   - Name: `techito-prod`
   - Server type: **App Server**
   - IP address: la IP estática **pública**. Private IP: la `172.26.x.x` de la pestaña *Networking*. Si se cruzan, el script termina con `ssh: connect to host 172.26.x.x port 22: Connection timed out`: borrar el servidor en Forge, crearlo de nuevo y correr el comando nuevo.
   - Ubuntu **24.04**, PHP **8.4**, Database **MySQL 8.4** (LTS).
3. **Guardar la contraseña sudo y la de la base** que muestra Forge (solo aparecen una vez).
4. Forge muestra un comando `wget ... forge.sh; bash forge.sh`. En Lightsail → **Connect using SSH**:
   ```bash
   sudo -i
   # pegar el comando de Forge
   ```
5. Esperar a que Forge marque el servidor como *Active* (10–15 min). Si la consola se corta a mitad, no relanzar el comando: mirar primero el estado en Forge. Si aparece el cartel *Pending kernel upgrade* o *Which services should be restarted?*: Enter y sigue; reiniciar recién cuando esté *Active*.
6. Servidor → **SSH Keys**: agregar tu clave pública (hoy `~/.ssh/id_rsa.pub` de la Mac) para poder entrar como `forge`.

### Paso 3 — Librerías de Chrome y swap

0. Reiniciar el servidor (Forge → servidor → Restart → Restart Server) para cargar el kernel que instaló el aprovisionamiento.

Forge no instala lo que necesita el Chrome de Puppeteer. En Forge → **Recipes**, recipe con usuario `root`, ejecutarla sobre el servidor:

```bash
set -e

# Espera a que termine cualquier apt en curso (actualizaciones automáticas de Ubuntu)
while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do sleep 5; done

apt-get update

# Google Chrome: trae como dependencias casi todas las librerías que necesita el Chrome de Puppeteer
wget -q -O /tmp/chrome.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
apt-get install -y /tmp/chrome.deb
rm -f /tmp/chrome.deb

# Librerías para Puppeteer (nombres de Ubuntu 24.04)
apt-get install -y \
  ca-certificates fonts-liberation fonts-noto-color-emoji \
  libasound2t64 libatk-bridge2.0-0 libatk1.0-0 libcups2 libdbus-1-3 libdrm2 \
  libgbm1 libgtk-3-0 libnspr4 libnss3 libx11-xcb1 libxcomposite1 libxdamage1 \
  libxkbcommon0 libxrandr2 libpango-1.0-0 libu2f-udev libvulkan1 xdg-utils

# Swap de 2 GB (Chrome + npm build en 2 GB de RAM). Si ya hay swap, no hace nada.
if ! swapon --show | grep -q .; then
  fallocate -l 2G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

swapon --show
```

Forge ya crea un swap de 1 GB, así que la recipe no agrega el suyo. Sumar otro de 1 GB a mano (como `ubuntu` desde la consola de Lightsail, sudo sin contraseña):

```bash
sudo fallocate -l 1G /swapfile2 && sudo chmod 600 /swapfile2 && sudo mkswap /swapfile2 && sudo swapon /swapfile2
echo '/swapfile2 none swap sw 0 0' | sudo tee -a /etc/fstab
```

No borrar locks de `dpkg` a mano ni purgar `snapd` (en AWS el `amazon-ssm-agent` es un snap). El Chrome del `.deb` no lo usa la app (`scripts/scrape.cjs` usa el que baja Puppeteer con `npm ci`): se instala por sus dependencias.

Lista completa de librerías en `docker/8.4/Dockerfile`. Verificar por SSH como `forge`: `free -h` muestra swap.

### Paso 4 — Base de datos

Forge → servidor → **Database**: crear la base `techito` (usuario `forge`, o uno nuevo `techito` con contraseña propia).

### Paso 5 — Sitio

1. Forge → servidor → **New Site**: dominio gratuito de Forge (`<nombre>.on-forge.com`) o el propio, PHP 8.4, web directory `/public`.
2. Conectar el repo de GitHub (`luc0/<repo>`), rama `main`. Forge agrega la deploy key.
3. **Environment**: completar con la sección 2 (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `APP_URL=https://<dominio>`, secretos de Groq, Telegram y VAPID).
4. **Deploy script**: el de la sección 3.
5. **Deploy Now**. Si `APP_KEY` está vacío: `php artisan key:generate` por SSH y volver a deployar.

### Paso 6 — HTTPS

Sitio → **SSL** → Let's Encrypt para el dominio. Sin HTTPS no andan las push ni la PWA.

### Paso 7 — Scheduler, Telegram y verificación

- Forge → servidor → **Scheduler**: `php /home/forge/<sitio>/artisan schedule:run`, cada minuto, usuario `forge`.
- Por SSH (`ssh forge@<ip>`, con la clave cargada en Forge → SSH Keys):
  ```bash
  cd ~/<sitio>
  php artisan telegram:set-webhook
  php artisan schedule:list
  php artisan rentals:analyze
  tail -50 storage/logs/laravel-*.log   # "✅ Sources captured."
  ```

Después: checklist de la sección 7.

## 11. Cambio de dominio

Hoy prod usa un dominio provisorio gratuito de Forge (`*.on-forge.com`). Esta guía es para cuando se pase al dominio definitivo (o se cambie de dominio por cualquier motivo).

### Qué se pierde

- **Suscripciones push**: quedan atadas al dominio (origen) donde se activaron. Con el dominio nuevo, **cada usuario tiene que volver a tocar "Activar notificaciones"**. Las del dominio viejo dejan de servir.
- **PWA instalada**: la app instalada en el celular apunta al dominio viejo. Hay que desinstalarla y volver a instalarla desde el dominio nuevo.
- **Sesiones**: las cookies son por dominio, todos tienen que volver a loguearse.

Esto pasa igual sea cual sea el dominio provisorio (Forge, sslip.io, etc.): conviene cambiar de dominio **una sola vez** y lo antes posible, antes de que haya muchos usuarios.

Telegram no se pierde: el vínculo está guardado en la base (`subscribers`), solo hay que volver a registrar el webhook.

### Pasos

1. Comprar el dominio (Lightsail → Domains & DNS → Register domain, o `.com.ar` en NIC Argentina).
2. DNS: registro `A` del dominio (y `www` si se usa) apuntando a la IP estática de Lightsail.
3. Forge → sitio → **Domains**: agregar el dominio nuevo y dejarlo como principal.
4. Forge → sitio → **SSL**: certificado Let's Encrypt para el dominio nuevo (sin HTTPS no andan push ni PWA).
5. Forge → sitio → **Environment**: `APP_URL=https://<dominio-nuevo>`.
6. Por SSH como `forge`:
   ```bash
   cd ~/<sitio>
   php artisan config:cache
   php artisan telegram:set-webhook
   ```
7. Opcional: redirigir el dominio viejo al nuevo (nginx en Forge), para que los links viejos (por ejemplo de notificaciones ya enviadas) sigan funcionando.
8. Avisar a los usuarios que vuelvan a activar las notificaciones (y reinstalen la PWA si la tenían).
9. Verificar con el checklist de la sección 7.
10. Anotar en `docs/history.md` la fecha y cómo salió.

## 12. Subir de versión de PHP (8.5 más adelante)

Forge instala varias versiones de PHP en paralelo (servidor → PHP) y en el sitio se elige cuál usa, sin recrear nada. Antes de cambiarla:

1. `composer update` en local (Laravel 12.3 y Pest 3.7 del lock son anteriores a PHP 8.5) y correr los tests.
2. Pasar Sail a 8.5 (hoy `docker/8.4/`, Sail 1.41 solo trae runtimes hasta 8.4) y correr los tests.
3. En Forge: instalar 8.5, cambiar la versión del sitio, deployar.

Hacerlo como tarea aparte, no junto con otros cambios de infraestructura.
