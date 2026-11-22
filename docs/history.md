# Historia

Lo que ya se hizo en producción, con fecha: migraciones de servidor, deploys con pasos únicos, cambios de dominio y verificaciones. Lo más nuevo arriba. Cómo está y cómo se opera hoy: [`docs/production.md`](production.md).

Cuando se aplica un paso de "Próximo deploy" de `production.md`, se mueve acá con la fecha.

## 2026-11-14 — Deploy del vencimiento de suscripciones

Migraciones con cambios de datos (`2026_11_14_*`):
- `add_last_seen_at_to_users_table`: guarda la última vez que cada usuario abrió la app.
- `add_expires_at_to_subscribers_table`: crea el suscriptor de los usuarios que no tenían y les pone a **todos** (incluidos los viejos sin cuenta) `expires_at = ahora + SUBSCRIPTION_DURATION_DAYS`, para que nadie pierda los avisos el día del deploy. El rollback no borra los suscriptores creados (no molestan).

Después del deploy: `php artisan schedule:list` tiene que mostrar `subscriptions:remind-expiring`.

## 2026-11-08 al 2026-11-13 — Migración de Hetzner a AWS Lightsail

Paso a paso (hoy como runbook) en `docs/production.md`, sección 10. Fechas: instancia el 2026-11-08; conexión a Forge, librerías de Chrome y swap el 2026-11-09; base, sitio, HTTPS, scheduler y Telegram hasta el 2026-11-13.

**Terminada.** Sitio `techito.on-forge.com` deployado con HTTPS, scheduler andando y webhook de Telegram registrado (prod usa `TechitoBot`; local usa su propio bot). Servidor viejo de Hetzner borrado. Pendiente: dominio definitivo y backups (ver `docs/production.md`, "Estado actual").

Decisiones tomadas:
- El servidor viejo de Forge (Hetzner, `157.180.34.53`) se borra, **en Forge y en Hetzner** (revisar que no queden Primary IPs, Volumes ni Snapshots sueltos).
- Base **MySQL 8.4 LTS** (decidido el 2026-11-09): igual que en local (Sail usa MySQL 8), así no hay que probar migraciones en dos motores. La de prod vieja era Postgres.
- La base **arranca vacía**: no se migra la vieja (Postgres) ni la local. La llenan `rentals:analyze` y `lands:analyze` (paso 7 del runbook). No correr `db:seed` (crea un usuario y avisos de prueba). El usuario propio se crea desde `/register`.
- El `.env` del sitio viejo se copió del panel de Forge (sitio → Environment). Al pegarlo, cambiar `APP_URL` y los `DB_*`.
- La config de Puppeteer está en una **Recipe** de Forge (es de la cuenta, no se pierde al borrar el servidor). La versión vieja fallaba en Ubuntu 24.04 (`libappindicator3-1` no existe), borraba locks de `dpkg` y no tenía swap: se reemplazó por la del paso 3 del runbook. No se usa como *post-provision recipe*: se corre a mano después.
- Las suscripciones push se pierden igual por el cambio de dominio: cada usuario las vuelve a activar.

## Primer deploy de la rama `rentals-rented-detection`

Esta rama trajo varias migraciones con cambios de datos. Con la migración a Lightsail la base arrancó vacía, así que estos pasos ya no hacen falta en el servidor actual. Pasos:

1. **Backup de la base** antes de deployar (Forge → Database → Backups, o `mysqldump`).
2. Configurar en el `.env` de producción: `APP_NAME=Techito`, `APP_TIMEZONE`, `ADMIN_PHONES`, `LOG_VIEWER_PHONES`, y revisar que `ANALYZE_RENTALS_DEBUG_MODE` y `SCRAPE_MOCK` no estén en `true`.
3. Deployar (el script corre `migrate --force`). Migraciones que se aplican:
   - `split_price_into_raw_amount_and_currency`: `price` pasa a texto; agrega `price_amount` y `price_currency`.
   - `add_image_and_rented_columns`: foto del aviso y `rented_at`.
   - `normalize_rental_sources`: decodifica los links y une los avisos duplicados (conserva la fecha de detección más vieja).
   - `fix_currency_of_legacy_rental_prices`: recalcula monto y moneda de los precios viejos a partir de la descripción.
   - `add_detail_columns`: superficie, dormitorios, cocheras, antigüedad, servicios.
   - `use_morph_alias_for_push_subscriptions` y `link_subscribers_to_users`: `anon_subscribers` pasa a `subscribers` con `user_id`; las suscripciones push existentes siguen funcionando.
   - `add_city_and_neighborhood` y `add_property_use`: columnas `city`, `neighborhood` y `property_use` (vacías hasta el paso 4).
   - `add_title`: título descriptivo generado por la IA (vacío hasta el paso 4; mientras tanto se muestra el título de la inmobiliaria).
4. Clasificar los avisos existentes (ciudad, barrio y uso; sin IA, instantáneo) y después completar el detalle (tarda unos minutos):
   ```bash
   php artisan rentals:classify
   php artisan rentals:fetch-details
   php artisan rentals:fetch-details --missing-titles   # títulos IA de los avisos que ya tenían detalle (1 llamada c/u)
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

### Si algo salía mal

- Volver al commit anterior y restaurar el backup de la base es lo más seguro: algunas migraciones transforman datos (links, precios) y su `down()` no recupera el formato original exacto.
- Todas las migraciones tienen `down()` y se probaron en PostgreSQL, pero un `migrate:rollback` en producción es el último recurso.

## Verificaciones hechas contra PostgreSQL

Contra PostgreSQL 16 (contenedor temporal), cuando producción usaba Postgres:
- `migrate:fresh` completo y la suite de tests (110 tests) pasan.
- Rollback de todas las migraciones nuevas y vuelta a migrar.
- Upgrade con datos en el formato viejo de producción: avisos con precio numérico, links codificados duplicados y una suscripción push con `App\Models\AnonSubscriber`. Resultado: duplicados unidos, precios con su moneda, suscripción funcionando con el alias nuevo.

## Checklist del primer servidor (Hetzner, PostgreSQL)

Ya no aplica: el servidor se borró en la migración a Lightsail.

🔧 Infraestructura
•	Servidor creado en Hetzner
•	Conectado a Forge como Custom VPS
•	Sitio creado correctamente (bolson-notification)
•	Nginx apuntando al sitio (no a /default)
•	PHP instalado (ver php8.3)
•	PostgreSQL instalado y funcionando

🔐 Seguridad & Configuración
•	.env cargado en Forge
•	APP_KEY generado tras deploy
•	APP_TIMEZONE seteado (opcional en .env)
•	.env cacheado con config:cache

💻 Código & Deploy
•	Repo conectado (SSH key agregada)
•	Deploy script revisado (opcionalmente mejorado)
•	Migraciones automáticas habilitadas (opcional)
•	Composer instalado sin --dev

⏰ Cron job
•	php artisan schedule:run configurado cada minuto desde Forge
•	Comandos Laravel con schedule() definidos
•	(opcional) timezone ajustado en config/app.php

💡 ¿Ideas para más adelante?
•	Agregar HTTPS con Let’s Encrypt desde Forge (pestaña SSL)
•	Configurar un job de backup automático (Forge o script manual)
•	Agregar monitoreo (logs, Sentry, Telegram, mail, etc.)
