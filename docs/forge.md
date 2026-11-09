# Laravel Forge

Notas sobre cómo está configurado Forge para prod y por qué. El alta paso a paso del servidor está en `docs/lightsail.md`; variables, deploy script y riesgos en `docs/production.md`; cambio de dominio en `docs/domain-change.md`.

## Servidor (`techito-prod`)

- Proveedor **Custom VPS** (Forge no se integra con Lightsail). App Server, Ubuntu 24.04, PHP 8.4, MySQL 8.4 LTS.
- **IP address = IP estática pública** de Lightsail; **Private IP = la `172.26.x.x`**. Si se cruzan, el script de aprovisionamiento termina con `ssh: connect to host 172.26.x.x port 22: Connection timed out` y hay que borrar el servidor en Forge y crearlo de nuevo.
- El comando `wget ... forge.sh; bash forge.sh` se corre **en el servidor** (consola SSH de Lightsail, después de `sudo -i`), no en la Mac. Mientras Forge dice *Waiting on your server to become ready* está esperando justamente eso.
- Durante el aprovisionamiento pueden aparecer los carteles *Pending kernel upgrade* / *Which services should be restarted?*: Enter y sigue. Reiniciar el servidor recién cuando Forge lo marque *Active*.
- **Contraseña sudo** del usuario `forge`: Forge la muestra una sola vez al crear el servidor. Si se pierde: `sudo passwd forge` desde la consola de Lightsail (el usuario `ubuntu` tiene sudo sin contraseña).
- **SSH**: se entra como `forge` con la clave agregada en servidor → SSH Keys (hoy `~/.ssh/id_rsa.pub` de la Mac).

## Recipes

- La de Puppeteer (librerías de Chrome + swap) se corre **como `root`** (usa `apt-get` y crea `/swapfile2`). Contenido en `docs/lightsail.md` paso 3.
- **No se usa como post-provision recipe**: se corre a mano después de que el servidor queda *Active*, así si falla se sabe que es la recipe y no el aprovisionamiento.
- Forge ya crea un swap de 1 GB (`/swapfile`); se agregó otro de 1 GB (`/swapfile2`) para llegar a 2 GB.

## Sitio

- Tipo **Laravel**, web directory `/public`, PHP 8.4.
- Dominio: `techito.on-forge.com`, el gratuito de Forge, mientras no haya uno propio. Mejor que `sslip.io`: no depende de un DNS de terceros ni choca con los límites de Let's Encrypt de un dominio compartido.
- Advanced settings: defaults, salvo:
  - **Zero-downtime deployments: desactivado.** Con zero-downtime cada deploy crea una carpeta de release nueva y corre `npm ci` desde cero, lo que vuelve a descargar el Chrome de Puppeteer (~150 MB) y deja un `node_modules` completo por release en un disco chico. Además el deploy script de `docs/production.md` está escrito para un deploy en el lugar (`cd /home/forge/<sitio>` + `git pull`). El costo es unos segundos de corte durante cada deploy, aceptable para esta app.
  - **User isolation: desactivado.** El sitio corre como `forge`: las rutas de los docs (`/home/forge/<sitio>`) y el Chrome de Puppeteer (en `~/.cache/puppeteer` de `forge`) dependen de eso.
- Octane, Horizon, queue workers y Reverb: no (la app no encola nada).
- Laravel Scheduler: activado (equivale al cron `schedule:run` cada minuto del paso 7 de `docs/lightsail.md`).
- Si al crear el sitio Forge dice *Forge was unable to create the environment file*: revisar por SSH que la carpeta del sitio tenga el código y volver a guardar el Environment.
- No deployar antes de cargar el **Environment**: sin los `DB_*` correctos el `migrate --force` del deploy falla.

## Base de datos

- Base `techito`, usuario propio `techito` (no el `forge` por defecto). Arranca vacía.

## Subir de versión de PHP (8.5 más adelante)

Forge instala varias versiones de PHP en paralelo (servidor → PHP) y en el sitio se elige cuál usa, sin recrear nada. Antes de cambiarla:

1. `composer update` en local (Laravel 12.3 y Pest 3.7 del lock son anteriores a PHP 8.5) y correr los tests.
2. Pasar Sail a 8.5 (hoy `docker/8.4/`, Sail 1.41 solo trae runtimes hasta 8.4) y correr los tests.
3. En Forge: instalar 8.5, cambiar la versión del sitio, deployar.

Hacerlo como tarea aparte, no junto con otros cambios de infraestructura.
