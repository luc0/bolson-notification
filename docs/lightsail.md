# Deploy en AWS Lightsail con Forge

Forge no tiene integración con Lightsail: se conecta la instancia como **Custom VPS** y Forge la aprovisiona (nginx, PHP, MySQL, Composer, Node, firewall, usuario `forge`). Requisitos de la app, variables y script de deploy: ver `docs/production.md`. Configuración de Forge y el porqué de cada opción: `docs/forge.md`.

## Estado de la migración (2026-11-09)

Quedamos en: **paso 5** (sitio `techito.on-forge.com` creado y repo clonado en `/home/forge/techito.on-forge.com`; falta cargar el Environment, el deploy script y el primer deploy). Pasos 2 y 3 hechos el 2026-11-09: PHP 8.4.27, MySQL 8.4.13, Node 22, Chrome 157, swap 2 GB (1 GB de Forge en `/swapfile` + `/swapfile2`). Revisar que el servidor viejo de Hetzner esté borrado.

Decisiones tomadas:
- El servidor viejo de Forge (Hetzner, `157.180.34.53`) se borra, **en Forge y en Hetzner** (revisar que no queden Primary IPs, Volumes ni Snapshots sueltos).
- Base **MySQL 8.4 LTS** (decidido el 2026-11-09): igual que en local (Sail usa MySQL 8), así no hay que probar migraciones en dos motores. La de prod vieja era Postgres.
- La base **arranca vacía**: no se migra la vieja (Postgres) ni la local. La llenan `rentals:analyze` y `lands:analyze` (paso 7). No correr `db:seed` (crea un usuario y avisos de prueba). El usuario propio se crea desde `/register`.
- El `.env` del sitio viejo se copió del panel de Forge (sitio → Environment). Al pegarlo, cambiar `APP_URL` y los `DB_*`.
- La config de Puppeteer está en una **Recipe** de Forge (es de la cuenta, no se pierde al borrar el servidor). La versión vieja fallaba en Ubuntu 24.04 (`libappindicator3-1` no existe), borraba locks de `dpkg` y no tenía swap: se reemplazó por la del paso 3. No se usa como *post-provision recipe*: se corre a mano después.
- Las suscripciones push se pierden igual por el cambio de dominio: cada usuario las vuelve a activar.

> Si ya hay prod andando en otro servidor: copiar de ese `.env` las claves `VAPID_*` (si se regeneran, todas las suscripciones push dejan de funcionar). Conservar los datos implicaría pasar de Postgres a MySQL: no se hace, la base arranca vacía.

## Paso 1 — Instancia ✅ (hecho el 2026-11-08)

- Instancia `Techito-Prod`: Ubuntu 24.04, General purpose 2 GB x86, con snapshots automáticos (de madrugada en Argentina).
- IP estática `Techito-Prod-IP` asociada.
- Firewall de Lightsail: 22, 80 y 443 abiertos a cualquier IPv4 e IPv6 (Forge necesita el 22 para conectarse; además configura `ufw` adentro).
- Sin dominio todavía: se usa el dominio gratuito de Forge (`*.on-forge.com`) como provisorio (mejor que `sslip.io`: no depende de un DNS de terceros ni de los límites de Let's Encrypt). Al pasar al definitivo: `docs/domain-change.md`.

> Forge necesita un servidor **recién creado**. Si ya se instalaron paquetes a mano (nginx, PHP, MySQL), crear una instancia nueva, pasarle la IP estática y borrar la vieja.

## Paso 2 — Conectar el servidor a Forge ✅ (hecho el 2026-11-09)

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
6. Servidor → **SSH Keys**: agregar tu `~/.ssh/id_ed25519.pub` para poder entrar como `forge`.

## Paso 3 — Librerías de Chrome y swap ✅ (hecho el 2026-11-09)

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

## Paso 4 — Base de datos

Forge → servidor → **Database**: crear la base `techito` (usuario `forge`, o uno nuevo `techito` con contraseña propia).

## Paso 5 — Sitio

1. Forge → servidor → **New Site**: dominio `<ip-con-guiones>.sslip.io`, PHP 8.4, web directory `/public`.
2. Conectar el repo de GitHub (`luc0/<repo>`), rama `main`. Forge agrega la deploy key.
3. **Environment**: completar con `docs/production.md` sección 2 (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `APP_URL=https://<dominio>`, secretos de Groq, Telegram y VAPID).
4. **Deploy script**: el de `docs/production.md` sección 3.
5. **Deploy Now**. Si `APP_KEY` está vacío: `php artisan key:generate` por SSH y volver a deployar.

## Paso 6 — HTTPS

Sitio → **SSL** → Let's Encrypt para el dominio. Sin HTTPS no andan las push ni la PWA.

## Paso 7 — Scheduler, Telegram y verificación

- Forge → servidor → **Scheduler**: `php /home/forge/<sitio>/artisan schedule:run`, cada minuto, usuario `forge`.
- Por SSH (`ssh forge@<ip>`, con la clave cargada en Forge → SSH Keys):
  ```bash
  cd ~/<sitio>
  php artisan telegram:set-webhook
  php artisan schedule:list
  php artisan rentals:analyze
  tail -50 storage/logs/laravel-*.log   # "✅ Sources captured."
  ```

Después: checklist de `docs/production.md` sección 7.

## Pendiente

- Comprar dominio definitivo: pasos y qué se pierde (suscripciones push, PWA) en `docs/domain-change.md`.
- Backups de la base: Forge → Database Backups (S3) o `mysqldump` diario por cron (el snapshot de disco con MySQL corriendo no es 100% confiable).
