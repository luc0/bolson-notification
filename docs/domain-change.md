# Cambio de dominio

Hoy prod usa un dominio provisorio gratuito de Forge (`*.on-forge.com`). Esta guía es para cuando se pase al dominio definitivo (o se cambie de dominio por cualquier motivo).

## Qué se pierde

- **Suscripciones push**: quedan atadas al dominio (origen) donde se activaron. Con el dominio nuevo, **cada usuario tiene que volver a tocar "Activar notificaciones"**. Las del dominio viejo dejan de servir.
- **PWA instalada**: la app instalada en el celular apunta al dominio viejo. Hay que desinstalarla y volver a instalarla desde el dominio nuevo.
- **Sesiones**: las cookies son por dominio, todos tienen que volver a loguearse.

Esto pasa igual sea cual sea el dominio provisorio (Forge, sslip.io, etc.): conviene cambiar de dominio **una sola vez** y lo antes posible, antes de que haya muchos usuarios.

Telegram no se pierde: el vínculo está guardado en la base (`subscribers`), solo hay que volver a registrar el webhook.

## Pasos

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
9. Verificar con el checklist de `docs/production.md` sección 7.
