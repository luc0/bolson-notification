# Dominios

El proyecto se organiza como un **monolito modular liviano**: cada dominio vive en `app/Domains/<Dominio>/` y adentro sigue las mismas convenciones de carpetas de Laravel (`Models`, `Enums`, `Data`, `Support`, `Services`...).

Lo que es propio del framework se queda donde Laravel lo espera:
- Controllers, requests y middleware en `app/Http/`.
- Comandos en `app/Console/Commands/`.
- Vistas en `resources/views/`.
- Migraciones en `database/migrations/`.
- Clientes de servicios externos compartidos (Groq, Puppeteer, fetch HTTP) en `app/Integrations/`.

## Regla de dependencias

```
Sources ──► Harvesting ──► Rentals ◄── Subscriptions ──► Members
                 │                          ▲
                 └───────► Lands         Payments
```

- **Rentals no depende de ningún otro dominio.** Es el núcleo que los demás leen o escriben.
- **Lands tampoco depende de ningún otro dominio** y está separado de Rentals: Harvesting lo escribe con su propio flujo (`lands:analyze`) y no tiene notificaciones.
- Harvesting escribe en Rentals. Subscriptions lee de Rentals (idealmente a partir de un evento `RentalPublished`).
- Agregar una fuente o un canal de notificación no debería tocar Rentals.

## Resumen

| Dominio | Qué resuelve | Tipo | Estado |
|---|---|---|---|
| [Sources](#sources) | Qué sitios se scrapean y cómo | Soporte | ✅ `app/Domains/Sources` |
| [Harvesting](#harvesting) | Pasar de la web a datos: captura, extracción con IA y enriquecimiento | **Core** | ✅ `app/Domains/Harvesting` |
| [Rentals](#rentals) | El catálogo de alquileres y su ciclo de vida | **Core** | ✅ `app/Domains/Rentals` |
| [Lands](#lands) | El catálogo de terrenos en venta | **Core** | ✅ `app/Domains/Lands` |
| [Subscriptions](#subscriptions) | Avisarle a cada persona los alquileres que le interesan | **Core** | ✅ `app/Domains/Subscriptions` |
| [Members](#members) | Identidad de los usuarios | Genérico | ✅ `app/Domains/Members` |
| [Payments](#payments) | Cobrar el acceso a las alertas | Genérico | Futuro (v1.1) |

**Core** es donde está el valor diferencial del producto. **Soporte** es necesario pero específico de este proyecto. **Genérico** conviene resolverlo de la forma más estándar posible.

---

## Sources

**Qué resuelve:** define de qué sitios se obtienen los alquileres y cómo leer cada uno.

| Subdominio | Descripción |
|---|---|
| Catálogo de fuentes | Lista de sitios en `config/sources.php` (clave, URL del listado, URL base de los links, selector a esperar). Alquileres en `sites` (`Source::all()` / `Source::find()`), terrenos en `land_sites` (`Source::lands()`). |
| Tipo de fuente | `SourceType`: inmobiliaria (`agency`, publica solo lo suyo) o portal (`portal`: Properati, MercadoLibre, agrega de muchos y puede repetir avisos). |
| Configuración por sitio | Particularidades de cada sitio: si renderiza con JS (`wait_for`), cómo se arman los links, etc. |

**Hoy:** `config/sources.php`, `app/Domains/Sources/` (`Data/Source`, `Enums/SourceType`).

Dónde se guardan las capturas y snapshots no es parte de Sources sino de Harvesting (`config/harvesting.php`).

## Harvesting

**Qué resuelve:** convierte las páginas de las fuentes en avisos estructurados y completos. Es el proceso que corre `rentals:analyze`.

| Subdominio | Descripción |
|---|---|
| Captura | Abrir cada listado con un navegador (Puppeteer, `scripts/scrape.cjs`) y guardar un HTML limpio. |
| Detección de cambios | Comparar la captura con el último snapshot para no reprocesar sitios que no cambiaron. |
| Extracción | Pasar el HTML a avisos estructurados con IA (título, link, precio, moneda, foto...). |
| Enriquecimiento | Visitar la página de detalle de cada aviso y extraer superficie, dormitorios, servicios, etc., sin inventar datos. |
| Detección de alquilados | Revisar la foto del aviso para detectar carteles de "ALQUILADO" o "RESERVADO". |
| Deduplicación | (Futuro, con portales) Reconocer el mismo aviso publicado en varias fuentes. |

**Hoy:** `config/harvesting.php`, `app/Domains/Harvesting/`:

| Clase | Subdominio |
|---|---|
| `Contracts/RentalScraper`, `Services/BrowserRentalScraper`, `Services/FakeRentalScraper` | Captura (orquesta todo el proceso de un sitio) |
| `Data/Capture`, `Support/CaptureStore` | Captura y detección de cambios |
| `Services/ListingExtractor` | Extracción |
| `Services/RentalImporter`, `Data/ImportResult` | Importación de los avisos en Rentals (alta, reactivación y desactivación) |
| `Services/RentalDetailService`, `RentalDetailFetcher`, `RentalDetailExtractor` | Enriquecimiento |
| `Services/RentalImageChecker` | Detección de alquilados |
| `Services/RentalClassifier` | Ciudad, barrio y uso (para vivir / comercial) al importar: texto del aviso → valor ya conocido (detalle) → `default_city` de la fuente |
| `Services/SourceCapturer` | Captura compartida por alquileres y terrenos (corre `scripts/scrape.cjs`) |
| `Services/AiListingExtractor` | Base de la extracción con IA (pedido con schema estricto, reintento y validación); la extienden `ListingExtractor` y `LandListingExtractor` |
| `Services/LandScraper`, `LandListingExtractor`, `LandImporter` | Terrenos: captura (en `captures/lands`), extracción e importación en Lands (alta, reactivación y desactivación). Sin enriquecimiento ni notificaciones por ahora. |

Usa `App\Integrations\Groq\GroqClient` para hablar con la IA.

## Rentals

**Qué resuelve:** es el catálogo de alquileres. Sabe qué es un alquiler, cuándo está disponible y cómo se describe, sin importar de dónde vino.

| Subdominio | Descripción |
|---|---|
| Disponibilidad | Ciclo de vida del aviso: activo, inactivo (ya no está en el sitio), alquilado (cartel en la foto) y antiguo (3+ meses). |
| Precio | Precio tal como lo publica la inmobiliaria más el monto y la moneda normalizados; detecta precios de relleno ("$1"). |
| Ubicación | Ciudad (`City`: El Bolsón, Lago Puelo, El Hoyo, Epuyén, Cholila) y barrio del aviso, resueltos del texto por `CityResolver` (alias + barrios conocidos). Son las opciones del filtro. |
| Características | Superficie, ambientes, dormitorios, baños, cocheras, antigüedad, servicios y datos extra limpios. |
| Identidad del aviso | Normalización del link para que el mismo aviso no se guarde dos veces. |
| Uso | `PropertyUse`: para vivir o local / comercial, resuelto del tipo de propiedad que nombra el título (`PropertyUseResolver`). |
| Búsqueda | `Queries/RentalSearch`: filtros del listado (ciudad, uso, rango de precio, ambientes, gas natural, fecha, incluir no disponibles), orden (`RentalSort`) y opciones con cantidades. |

**Hoy:** `app/Domains/Rentals/`.

## Lands

**Qué resuelve:** es el catálogo de terrenos (lotes, fracciones, chacras) en venta. Está separado de Rentals: no comparte modelo, tabla ni pantalla.

| Subdominio | Descripción |
|---|---|
| Disponibilidad | Activo (sigue publicado en el sitio) o no disponible. |
| Precio | Texto de la inmobiliaria + monto y moneda normalizados (montos menores a 1.000 se toman como relleno). Precio por m² cuando hay superficie. |
| Superficie | `area` en m² (la IA convierte hectáreas); se muestra en ha desde 1 ha. |
| Búsqueda | `Queries/LandSearch`: incluir no disponibles y orden (`LandSort`: más nuevos, precio con dólares primero, más grandes). |

**Hoy:** `app/Domains/Lands/` (`Models/Land`, `Enums/LandSort`, `Queries/LandSearch`). Pantalla: pestaña "Terrenos" (`/terrenos`, `LandController`, `resources/views/lands`). Comando: `lands:analyze` (todos los días a las 18:00).

Pendiente: ciudad/barrio para filtrar (hoy `City` vive en Rentals; habría que moverlo a un lugar compartido para no acoplar Lands a Rentals) y notificaciones.

## Subscriptions

**Qué resuelve:** avisarle a cada persona solo los alquileres que le sirven, por el canal que prefiera.

| Subdominio | Descripción |
|---|---|
| Suscriptores | Quién recibe alertas: un `Subscriber` por Member (`user_id`), con una o varias suscripciones push (una por navegador). Las suscripciones viejas, anteriores a esto, no tienen Member. |
| Preferencias y matching | (Futuro) Qué le interesa a cada uno (zona, precio, gas natural...) y qué avisos nuevos le corresponden. |
| Canales | Cómo se envía: web push (hoy), WhatsApp (Twilio/Kapso, apagado) y Telegram (futuro). |
| Envíos | Armado y envío de la notificación ("N alquileres nuevos"). |

**Hoy:** `app/Domains/Subscriptions/`:

| Clase | Subdominio |
|---|---|
| `Models/Subscriber` (alias `subscriber` en el morph map) | Suscriptores |
| `Services/NewRentalsNotifier`, `Notifications/NewRentalsNotification` | Envíos |
| `Contracts/WhatsappSender`, `Services/TwilioWhatsappSender`, `Services/KapsoWhatsappSender`, `Models/WhatsappSubscriber` | Canales (WhatsApp, apagado) |

El alta y la baja de la suscripción push están en `app/Http/Controllers/PushSubscriptionController` (rutas web `push-subscriptions.*`, solo para Members logueados). Si un navegador lo suscribe otro Member, la suscripción pasa al último. Al borrar la cuenta se borran el suscriptor y sus suscripciones push.

## Members

**Qué resuelve:** la identidad de las personas que usan la app.

| Subdominio | Descripción |
|---|---|
| Registro y acceso | Alta y login con teléfono y contraseña. |
| Perfil | Datos de la cuenta y baja. |
| Recuperación | (Futuro) Recuperar la cuenta. |
| Consentimiento y privacidad | (Futuro) Consentimiento para recibir mensajes y cifrado de teléfonos. |

**Hoy:** `app/Domains/Members/Models/User` (alias `user` en el morph map; `config/auth.php` apunta ahí). Los controllers de `app/Http/Controllers/Auth`, `ProfileController` y sus requests quedan en `app/Http`.

## Payments

**Qué resuelve:** cobrar el acceso a las alertas (v1.1, pago único de un mes).

| Subdominio | Descripción |
|---|---|
| Planes | Qué se vende y cuánto dura (1 mes). |
| Checkout | Pago con Mercado Pago. |
| Vencimientos | Cuándo vence el acceso; corta o habilita las alertas en Subscriptions. |

**Hoy:** no existe todavía.
