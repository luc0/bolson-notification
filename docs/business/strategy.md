# Estrategia de negocio

Decisiones de producto y negocio (no técnicas). Se va completando a medida que se definen cosas.
Última actualización: 2026-11-14.

**Implementado (2026-11-14):** planes gratis/suscripto con retraso de 3 días, alta automática al registrarse por la web, vencimiento a 1 mes, renovación manual (`subscriptions:extend`), aviso de vencimiento solo a quien usa la app, y registro de la última vez que cada usuario abrió la app. Falta: el alta desde el bot de Telegram (ver `todo.md`).

## Propuesta de valor

En El Bolsón un alquiler se publica y muchas veces en una semana ya está alquilado. Lo que vale es **enterarse rápido**:
Techito junta los avisos de todas las inmobiliarias en un solo lugar y avisa apenas aparece uno nuevo.

Por eso lo que se cobra es la **velocidad** (avisos al instante y ver lo último), no el acceso a los datos en sí.

## Planes

| | Registrado (gratis) | Suscripto |
|---|---|---|
| Ver el listado y filtros | Sí, con retraso: solo avisos agregados hace **3 días o más** | Sí, todo, en el momento |
| Avisos por Telegram y/o push | No | Sí |
| Terrenos | No (por ahora solo admin) | No (por ahora solo admin) |

- **Registrarse** = tener cuenta (celular + clave). Te deja usar la app gratis, con retraso.
- **Suscribirse** = tener una suscripción vigente (fecha de vencimiento a futuro). Te da los avisos y ver lo último.
- Al vencer la suscripción no se borra nada: el usuario vuelve a ser "registrado" (deja de recibir avisos y vuelve a ver con retraso).

### Retraso del plan gratis: 3 días (a revisar con datos)

Por qué 3 días, si un alquiler dura más o menos una semana publicado:
- **Menos (1-2 días):** la diferencia con suscribirse es chica; muchos no ven motivo para pagar.
- **3 días:** el gratis ve avisos que todavía pueden estar disponibles (sirve para ver que la app funciona), pero llega tarde a los mejores. Se nota la diferencia.
- **Más (5-7 días):** el gratis ve casi solo avisos ya alquilados; la app parece vieja o rota y no engancha.

Gancho de conversión: al usuario gratis mostrarle algo como "Hay N alquileres nuevos de los últimos 3 días. Suscribite para verlos y recibir avisos".

Cómo se calcula: con la fecha en que Techito agregó el aviso (`created_at` del alquiler, ya existe). Si se agregó hace 3 días o más, el gratis lo ve; si se agregó ayer, no.

El número va en config (no hardcodeado) para poder cambiarlo sin deploy de código.

## Táctica: medir cómo se usa la app

En la beta lo más importante no es cuántos se registran sino **si la usan, cuánto y cómo**. Sin eso no hay forma de saber si vale la pena cobrar ni qué mejorar. Todas las decisiones de la beta (1 mes con renovación a pedido, aviso de vencimiento solo a quien la usa) apuntan a juntar esa información.

Qué medir:
- **Si la usan:** última vez que cada usuario abrió la app. Es también la condición del aviso de vencimiento.
- **Cuánto:** cuántos días por semana la abre cada usuario (activos en la última semana / mes).
- **Cómo:** por dónde llega (toca un aviso de Telegram o push, o entra directo), qué filtros usa, a qué alquileres entra (clic al aviso de la inmobiliaria).
- **Retención:** quién pide renovar y quién la deja vencer.
- **Avisos:** cuántos se mandan y cuántos terminan en una visita.

Primer paso (con la suscripción): guardar la última vez que el usuario abrió la app. El resto se va sumando de a poco.

## Etapas

### Beta gratis (v1.0): registrarse te suscribe

- **Registrarse = suscribirse automáticamente**, por las dos vías:
  - Registro web (queda abierto).
  - Alta desde el bot de Telegram (solo admin): un comando con el celular crea el usuario (clave inicial 12345) y lo suscribe.
- Duración de la suscripción en la beta: **1 mes** (en config, para poder cambiarla).
- **Renovación a pedido:** al vencer, el usuario me pide renovar y yo le doy otro mes. Suma desde el vencimiento si todavía no venció, o desde hoy si ya venció.
  - Por qué: que pida renovar muestra que la usa de verdad, me abre una charla para pedirle feedback y lo acostumbra al ciclo mensual que después se cobra (pasar de "meses gratis" a "pagás cada mes" se siente como perder algo).
  - Costo: trabajo manual mío por cada renovación; en la beta son pocos usuarios.
- Los suscriptores que ya existen arrancan con la misma duración para no cortarles los avisos.

#### Aviso de vencimiento

- Unos días antes de vencer se avisa por **Telegram** (si lo tiene vinculado) y **push** (si las activó): "Tu suscripción vence el dd/mm, pedime renovarla".
- **Solo se manda si el usuario abrió la app en los últimos 5 días** (configurable). Si no la está usando, se la deja vencer sin avisar: que no la renueve también es un dato.
- Pendiente: cuántos días antes de vencer se avisa (propuesta: 3).

### Beta paga (v1.1): Mercado Pago

- Pago único que da 1 mes de suscripción (cada pago extiende el vencimiento).
- Registrarse deja de suscribir automáticamente: te registrás gratis (plan con retraso) y pagás para suscribirte.

## Pendiente de definir

- Precio de la suscripción (v1.1).
- ¿Los avisos respetan los filtros del usuario (ciudad, precio, ambientes) o se avisa de todo?
- Suscriptores viejos sin cuenta (de antes de que existieran usuarios): ¿se les corta o se los migra?
