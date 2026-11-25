# Arquitectura

El proyecto se organiza como un **monolito modular liviano**: cada dominio vive en `app/Domains/<Dominio>/` y adentro sigue las mismas convenciones de carpetas de Laravel (`Models`, `Enums`, `Data`, `Support`, `Services`...).

Lo que es propio del framework se queda donde Laravel lo espera:
- Controllers, requests y middleware en `app/Http/`.
- Comandos en `app/Console/Commands/`.
- Vistas en `resources/views/`.
- Migraciones en `database/migrations/`.
- Clientes de servicios externos compartidos (Groq, Telegram, ArgentinaDatos, Puppeteer, fetch HTTP) en `app/Integrations/`.

Detalles técnicos que valen para todos los dominios:
- Los modelos fuera de `App\Models` necesitan `#[UseFactory(...)]` (y `$model` en la factory): Laravel no adivina el nombre de la factory.
- Los modelos polimórficos se registran en `Relation::morphMap()` (`AppServiceProvider::boot()`), así los tipos guardados en la base no dependen del nombre de la clase.

## Regla de dependencias

```
                         Market (lee Rentals, Lands y Houses)

Sources ──► Harvesting ──► Rentals ◄── Subscriptions ──► Members
                 │            │             ▲
                 │            ▼          Payments
                 │        Locations ◄──┐
                 │            ▲        │
                 ├───────► Lands       │
                 └───────► Houses ─────┘
```

- **Locations** es un núcleo compartido chico (ciudades de la Comarca y cómo reconocerlas en un texto): no depende de nadie y lo usan Rentals, Lands, Houses y Harvesting.
- **Rentals solo depende de Locations.** Es el núcleo que los demás leen o escriben.
- **Lands solo depende de Locations** y está separado de Rentals: Harvesting lo escribe con su propio flujo (`lands:analyze`) y no tiene notificaciones.
- **Houses** (casas en venta) es igual que Lands: solo depende de Locations, flujo propio (`houses:analyze`) y sin notificaciones. Rentals, Lands y Houses nunca dependen entre sí.
- **Market** solo lee de Rentals, Lands y Houses (y de Locations para las ciudades); ninguno depende de él.
- Harvesting escribe en Rentals, Lands y Houses. Subscriptions lee de Rentals (idealmente a partir de un evento `RentalPublished`).
- Agregar una fuente o un canal de notificación no debería tocar Rentals.

Esta regla la verifican tests de arquitectura de Pest (*fitness functions*) en `tests/Arch/DependencyRuleTest.php`: si un cambio la rompe, el test falla. Para cambiar la regla: un ADR nuevo y actualizar el test y este diagrama juntos.

## Resumen

| Dominio | Qué resuelve | Tipo | Estado |
|---|---|---|---|
| [Sources](#sources) | Qué sitios se scrapean y cómo | Soporte | ✅ `app/Domains/Sources` |
| [Harvesting](#harvesting) | Pasar de la web a datos: captura, extracción con IA y enriquecimiento | **Core** | ✅ `app/Domains/Harvesting` |
| [Locations](#locations) | Ciudades y barrios de la Comarca, compartidos por los catálogos | Soporte | ✅ `app/Domains/Locations` |
| [Rentals](#rentals) | El catálogo de alquileres y su ciclo de vida | **Core** | ✅ `app/Domains/Rentals` |
| [Lands](#lands) | El catálogo de terrenos en venta | **Core** | ✅ `app/Domains/Lands` |
| [Houses](#houses) | El catálogo de casas en venta | **Core** | ✅ `app/Domains/Houses` |
| [Market](#market) | Cómo cambian los precios mes a mes (sección Análisis) | Soporte | ✅ `app/Domains/Market` |
| [Subscriptions](#subscriptions) | Avisarle a cada persona los alquileres que le interesan | **Core** | ✅ `app/Domains/Subscriptions` |
| [Members](#members) | Identidad de los usuarios | Genérico | ✅ `app/Domains/Members` |
| [Payments](#payments) | Cobrar el acceso a las alertas | Genérico | Futuro (v1.1) |

**Core** es donde está el valor diferencial del producto. **Soporte** es necesario pero específico de este proyecto. **Genérico** conviene resolverlo de la forma más estándar posible.

---

## Sources

**Qué resuelve:** define de qué sitios se obtienen los avisos y cómo leer cada uno.

| Subdominio | Descripción |
|---|---|
| Catálogo de fuentes | `config/sources.php` es la única fuente de verdad (clave, tipo, URL del listado, URL base de los links, selector a esperar `wait_for`). Alquileres en `rental_sites` (`Source::rentals()` / `Source::findRental($key)`), terrenos en `land_sites` (`Source::lands()`), casas en venta en `house_sites` (`Source::houses()`). Para sumar un sitio, se agrega ahí y nada más. |
| Tipo de fuente | `SourceType`: inmobiliaria (`agency`, publica solo lo suyo) o portal (`portal`: Properati, MercadoLibre, agrega de muchos y puede repetir avisos). |
| Configuración por sitio | Particularidades de cada sitio: si renderiza con JS (`wait_for`), cómo se arman los links, listados paginados (`next_page_selector`: las páginas se unen en una sola captura para que las siguientes no se desactiven), `default_city` cuando la fuente es de una sola ciudad, `listing_id_pattern` (regex con el id propio de la inmobiliaria en el link, `Source::listingIdFrom()`; solo en sitios con id estable: los que usan solo slug, como Tornero, Di Salvo o Ester Cortez, no lo tienen). |

**Hoy:** `config/sources.php`, `app/Domains/Sources/` (`Data/Source`, `Enums/SourceType`).

Los listados largos (30+ avisos) hacen que la IA devuelva un subconjunto distinto en cada corrida: partir las fuentes grandes por ciudad (una fuente por ciudad con `default_city`, como `disalvo_*`).

Dónde se guardan las capturas y snapshots no es parte de Sources sino de Harvesting (`config/harvesting.php`).

## Harvesting

**Qué resuelve:** convierte las páginas de las fuentes en avisos estructurados y completos. Es el proceso que corren `rentals:analyze`, `lands:analyze` y `houses:analyze`.

| Subdominio | Descripción |
|---|---|
| Captura | Abrir cada listado con un navegador (Puppeteer, `scripts/scrape.cjs`, que recibe los sitios por stdin) y guardar un HTML limpio. Los links que están en un `onclick` (sin `href`) se conservan como `href`. |
| Detección de cambios | Comparar la captura con el último snapshot para no reprocesar sitios que no cambiaron. |
| Extracción | Pasar el HTML a avisos estructurados con IA (título, link, precio, moneda, foto...). |
| Enriquecimiento | Visitar la página de detalle de cada aviso y extraer superficie, dormitorios, servicios, título descriptivo, etc., sin inventar datos. |
| Detección de alquilados | Revisar la foto del aviso para detectar carteles de "ALQUILADO" o "RESERVADO". |
| Clasificación | Ciudad, barrio y uso de cada aviso al importar (ver [Locations](#locations)). |
| Identidad y precios | Reconocer un aviso ya guardado aunque cambie su URL y registrar sus cambios de precio (ver [Identidad del aviso e historial de precios](#identidad-del-aviso-e-historial-de-precios)). |
| Deduplicación | (Futuro, con portales) Reconocer el mismo aviso publicado en varias fuentes. |

**Flujo principal** (`app/Console/Commands/AnalyzeRentals.php`, `rentals:analyze`, programado en `routes/console.php`): captura → extracción con IA → guardado → enriquecimiento del detalle → notificación.

**Hoy:** `config/harvesting.php`, `app/Domains/Harvesting/`. Alquileres, terrenos y casas usan las mismas carpetas; las clases llevan el nombre del catálogo, de forma simétrica (`BrowserRentalScraper` / `BrowserLandScraper` / `BrowserHouseScraper`, `…ListingExtractor`, `…Importer`). Compartido:

| Clase | Subdominio |
|---|---|
| `Services/SourceCapturer` | Captura (corre `scripts/scrape.cjs`) |
| `Data/Capture`, `Support/CaptureStore` | Captura y detección de cambios (carpetas por catálogo en `config/harvesting.php` `rentals.*` / `lands.*` / `houses.*`: `CaptureStore::forRentals()` / `forLands()` / `forHouses()`) |
| `Services/AiListingExtractor` | Base de la extracción con IA (pedido con schema estricto, reintento y validación) |
| `Data/ImportResult` | Resultado de una importación |
| `Services/ListingMatcher`, `Data/ListingMatch`, `Enums/MatchMethod` | Identidad: busca el aviso guardado por URL y después por `external_id` dentro de la misma fuente |
| `Support/PriceChangeDetector`, `Enums/PriceReview` | Decide qué hacer con el precio leído: sin cambios, pendiente (primera lectura distinta) o confirmado (la segunda lectura coincide); ignora precios ilegibles y saltos de más de `harvesting.price_changes.max_ratio` veces |

**Alquileres:**

| Clase | Subdominio |
|---|---|
| `Contracts/RentalScraper`, `Services/BrowserRentalScraper` (Puppeteer), `Services/FakeRentalScraper` (`SCRAPE_MOCK=true`) | Orquesta todo el proceso de un sitio |
| `Services/RentalListingExtractor` | Extracción |
| `Services/RentalImporter` | Importación de los avisos en Rentals (alta, reactivación, cambio de precio o de URL y desactivación) + revisión de la foto |
| `Services/RentalDetailService`, `RentalDetailFetcher`, `RentalDetailExtractor` | Enriquecimiento. Completar los que faltan: `rentals:fetch-details` (`--missing-titles` para los títulos) |
| `Services/RentalImageChecker` | Detección de alquilados |
| `Services/RentalClassifier` | Ciudad, barrio y uso (para vivir / comercial) al importar: texto del aviso → valor ya conocido (detalle) → `default_city` de la fuente |

**Terrenos:**

| Clase | Subdominio |
|---|---|
| `Services/BrowserLandScraper` | Orquesta todo el proceso de un sitio (capturas en `storage/app/captures/lands`) |
| `Services/LandListingExtractor` | Extracción. Usa `services.groq.land_reasoning_effort` (default `medium`): con `low` la IA se rinde con listados largos y devuelve una lista vacía. |
| `Services/LandImporter` | Importación en Lands (alta, reactivación, cambio de precio o de URL y desactivación). Sin enriquecimiento ni notificaciones por ahora. |
| `Services/LandClassifier` | Ciudad y barrio al importar |

**Casas:**

| Clase | Subdominio |
|---|---|
| `Services/BrowserHouseScraper` | Orquesta todo el proceso de un sitio (capturas en `storage/app/captures/houses`) |
| `Services/HouseListingExtractor` | Extracción (`services.groq.house_reasoning_effort`, default `medium`; con `high` se queda sin tokens) |
| `Services/HouseImporter` | Importación en Houses (alta, reactivación, cambio de precio o de URL y desactivación) |
| `Services/HouseClassifier` | Ciudad y barrio al importar |

Usa `App\Integrations\Groq\GroqClient` para hablar con la IA (modelos en `config/services.php`: `GROQ_MODEL` y `GROQ_REASONING_EFFORT` para extraer avisos, `GROQ_VISION_MODEL` para las fotos).

### Detalle de cada aviso

El listado no trae superficie, dormitorios ni servicios: están en la página de detalle de cada aviso.
- `RentalDetailService` (corre dentro de `rentals:analyze`) baja el detalle por HTTP (`RentalDetailFetcher`, con reintentos: los sitios de Tokko dan 500 intermitentes) y lo extrae con la IA (`RentalDetailExtractor`).
- Solo datos explícitos: cada valor se descarta si la página no tiene la palabra que lo respalda (ej.: antigüedad sin "antigüedad" / "estrenar").
- Una vez por aviso disponible, hasta 3 intentos (`details_attempts`). Completar a mano: `rentals:fetch-details`.

### Detección de alquilados por foto

Algunas inmobiliarias (ej.: Punto Patagonia) no despublican lo alquilado: le ponen un cartel "ALQUILADO" / "RESERVADO" a la foto.
- `scripts/scrape.cjs` reemplaza cada `<img src>` por un id corto (`#img-3`) y guarda las URLs reales en `storage/app/captures/<sitio>.images.json`. Descarta íconos (menos de 80 px).
- La IA devuelve el id de la foto principal y `BrowserRentalScraper` lo traduce a la URL.
- `RentalImageChecker` le pregunta a un modelo de visión si la foto tiene el cartel. Cada URL se revisa una sola vez (`image_checked_url`).
- Si da alquilado se pone `rented_at`: no se notifica y se muestra en "No disponibles". Si la revisión falla, el aviso queda disponible y se reintenta.

Para forzar que se re-analice un sitio: borrar su snapshot (`storage/app/snapshots/`).

### Identidad del aviso e historial de precios

Igual para alquileres, terrenos y casas (ver ADR-003):
- Si la captura no cambió no se re-analiza; si cambió, el importer busca cada aviso con `ListingMatcher`: por `source` (URL normalizada) y, si no está, por `external_id` (id de la inmobiliaria en el link, `listing_id_pattern`) dentro de la misma fuente (`site_path`). Si lo encuentra por id con otra URL, actualiza la URL y no lo avisa como nuevo. Si hay varios, toma el activo y más nuevo.
- Ventana de reaparición (ADR-005): solo se reconocen avisos activos o desactivados hace menos de `harvesting.relisting_after_days` (30) días (`deactivated_at`). Uno que vuelve después es una disponibilidad nueva: se guarda como aviso nuevo (con su fecha y su historial) y se avisa. Por eso `source` ya no es `unique`: la misma URL puede estar en más de una fila.
- El aviso siempre tiene el último precio confirmado. Cada cambio confirmado se guarda en la tabla de historial de su catálogo (`rental_price_changes`, `land_price_changes`, `house_price_changes`): una fila al darlo de alta (`created`), otra por cada cambio de precio y otra cuando se lo reconoce con otra URL.
- Confirmación en dos lecturas (ADR-004): un precio distinto leído una vez queda en `pending_price*` sin tocar el precio. Mientras la fuente tenga avisos activos con precio pendiente (`ImportResult::pendingPriceCount`), el scraper **no guarda el snapshot**, así que la próxima corrida la vuelve a leer aunque la página no haya cambiado. Si la segunda lectura da el mismo precio nuevo, se confirma; si da el precio conocido o no se puede leer, se descarta; si da otro, pasa a ser el pendiente. Al desactivar un aviso se borra su pendiente (si no, la fuente se releería para siempre).
- `PriceChangeDetector` además: un precio ilegible ("Consultar", o un precio de relleno en venta) no pisa el conocido, y un salto de más de `max_ratio` veces (3, `config/harvesting.php`) se ignora y se loguea, como red de seguridad.
- Cada fila guarda `match_method` (`created`, `url`, `external_id`) y `match_confidence` (0 a 1; hoy todo es 1.0). Los métodos aproximados (huella, foto, texto) van a tener confianza menor (pendiente en `docs/todo.md`).
- Solo se sigue el precio: título, descripción y características quedan como se vieron la primera vez.

## Rentals

**Qué resuelve:** es el catálogo de alquileres. Sabe qué es un alquiler, cuándo está disponible y cómo se describe, sin importar de dónde vino.

| Subdominio | Descripción |
|---|---|
| Disponibilidad | Ciclo de vida del aviso: activo (`active`, sigue en el sitio), inactivo, alquilado (`rented_at`, cartel en la foto) y antiguo (detectado hace 3+ meses, `Rental::OLD_AFTER_MONTHS`). Para filtrar los disponibles: `Rental::available()`. |
| Precio | `price` tal como lo publica la inmobiliaria + `price_amount` / `price_currency` normalizados (`Support/PriceParser`); detecta precios de relleno ("$1"). Siempre el último precio; el historial está en `Models/RentalPriceChange` (`priceChanges()`, `firstKnownPrice()`, `previousPrice()`). La tarjeta muestra "📉 Bajó de precio" con el precio anterior tachado (`hasPriceDrop()`; las búsquedas precargan `priceChanges`). |
| Ubicación | `location` es el texto crudo de la inmobiliaria; `city` y `neighborhood` se resuelven de ahí (ver [Locations](#locations)). Si no aparece: la ciudad del detalle (IA, solo si está en la página) y después el `default_city` de la fuente. Son las opciones del filtro. |
| Características | Del detalle: `covered_area`, `total_area`, `bedrooms`, `parking_spaces`, `age_years`, `services` (json de valores de `Enums/Utility`) y datos extra limpios. |
| Identidad del aviso | `source` es el link del aviso, guardado decodificado (`Support/RentalLink`) para que el mismo aviso no se guarde dos veces. `external_id` es el id de la inmobiliaria sacado del link (si la fuente tiene `listing_id_pattern`), para reconocerlo si cambia la URL. `deactivated_at`: cuándo dejó de publicarse; si vuelve pasados 30 días es un aviso nuevo, así que una URL puede repetirse (`source` no es único). `site_path` es el `listing_url` de la fuente de donde salió. `source_path` no se usa (pendiente de borrar). |
| Títulos | `content` es el título de la inmobiliaria; `title` es un título corto y descriptivo que escribe la IA desde el detalle (`RentalDetailExtractor`). La tarjeta muestra `title` y, si no hay, `content`. |
| Uso | `property_use` (`PropertyUse`: para vivir / comercial), resuelto del tipo de propiedad que nombra el título (`PropertyUseResolver`); si no, del detalle (IA, solo si el tipo está en la página). |
| Búsqueda | `Queries/RentalSearch`. Filtros con chips de un clic (`<x-filter-chip>`): ciudad, y en "Más filtros" (`<details>`, se abre solo cuando alguno no está en su valor por defecto) uso (por defecto para vivir; `uses=all` para todos), rango de precio (`PriceRange`: rangos en pesos + dólares), ambientes (4+ agrupados) y gas natural; incluir no disponibles; orden (`RentalSort`: más nuevos por defecto sin chip; los chips de precio ordenan pesos, después dólares, después sin precio). |

**Hoy:** `app/Domains/Rentals/`. Ciudad, barrio y uso los pone `Harvesting\Services\RentalClassifier` al importar; para reclasificar sin IA: `rentals:classify`.

## Lands

**Qué resuelve:** es el catálogo de terrenos (lotes, fracciones, chacras) en venta. Está separado de Rentals: no comparte modelo, tabla ni pantalla.

| Subdominio | Descripción |
|---|---|
| Disponibilidad | Activo (sigue publicado en el sitio) o no disponible. |
| Precio | Mismas columnas que `Rental` (`source`, `external_id`, `site_path`, `content`, precio): texto de la inmobiliaria + monto y moneda normalizados (montos menores a 1.000 se toman como relleno). Precio por m² cuando hay superficie. Último precio + historial en `Models/LandPriceChange`, "Bajó de precio" en la tarjeta. |
| Superficie | `area` en m² (la IA convierte hectáreas); se muestra en ha desde 1 ha. |
| Ubicación | Ciudad y barrio (ver [Locations](#locations)), resueltos al importar por `Harvesting\Services\LandClassifier`: texto del aviso → valor ya conocido → `default_city` de la fuente. Reclasificar sin IA: `lands:classify`. |
| Búsqueda | `Queries/LandSearch`: ciudad (chips, incluye "Sin especificar"), incluir no disponibles y orden (`LandSort`: más nuevos, precio con dólares primero, más grandes). |

**Hoy:** `app/Domains/Lands/` (`Models/Land`, `Enums/LandSort`, `Queries/LandSearch`). Pantalla: pestaña "Terrenos" (`/terrenos`, `LandController`, `resources/views/lands`), solo admins (ver [Members](#members)). Comando: `lands:analyze` (en producción los lunes a las 18:00; en modo debug cada 3 minutos, como los alquileres).

Pendiente: notificaciones.

## Houses

**Qué resuelve:** es el catálogo de casas en venta. Igual que Lands, separado de Rentals: no comparte modelo, tabla ni pantalla. Por ahora solo lo ven los admins.

| Subdominio | Descripción |
|---|---|
| Disponibilidad | Activa (sigue publicada en el sitio) o no disponible. |
| Precio | Mismas columnas que `Land`: texto de la inmobiliaria + monto y moneda normalizados (montos menores a 1.000 se toman como relleno). Último precio + historial en `Models/HousePriceChange`, "Bajó de precio" en la tarjeta. |
| Características | En lugar de `area`: dormitorios, baños, superficie cubierta (`covered_area`) y del terreno (`total_area`, en ha desde 1 ha), más datos extra de la IA. |
| Ubicación | Ciudad y barrio (ver [Locations](#locations)), resueltos al importar por `Harvesting\Services\HouseClassifier`. Reclasificar sin IA: `houses:classify`. |
| Búsqueda | `Queries/HouseSearch`: ciudad, incluir no disponibles y orden (`HouseSort`: más nuevas, precio con dólares primero, más grandes por m² cubiertos). |

**Hoy:** `app/Domains/Houses/` (`Models/House`, `Enums/HouseSort`, `Queries/HouseSearch`). Pantalla: pestaña "Casas" (`/casas`, `HouseController`, `resources/views/houses`), solo admins. Comando: `houses:analyze` (en producción los lunes a las 18:30). Fuentes en `config/sources.php` `house_sites`. Sin notificaciones.

## Market

**Qué resuelve:** cómo evolucionan los precios de alquileres, casas y terrenos mes a mes (pestaña "Análisis", `/analisis`, `MarketController`, solo admins).

| Subdominio | Descripción |
|---|---|
| Series | Una clase por gráfico (`Contracts/PriceSeries`, en `Series/`: `ResidentialRentals`, `CommercialRentals`, `HousesForSale`, `LandsForSale`): qué avisos entran, qué precio se mide (`PriceUnit`: total, por m² o por m² cubierto), en qué monedas, si se agrupa por dormitorios (`groupsByBedrooms()`) y por rango de superficie. Hoy: alquileres para vivir y comerciales (ARS y USD), casas en venta (USD por m² cubierto, `covered_area`) y terrenos en venta (USD por m²); los avisos sin superficie quedan afuera. Se listan en `config/market.php`. |
| Cotización | `Services/ExchangeRates`: promedio mensual del dólar (`market.dollar`, blue) bajado de argentinadatos.com (`App\Integrations\ArgentinaDatos`) a `exchange_rates`. Cada aviso se convierte con el promedio de su mes antes de calcular la mediana. |
| Estadísticas | `Services/PriceStatsCalculator`: de los avisos **publicados en el mes** (`created_at`, así se puede reconstruir la historia), con el **primer precio conocido** de cada uno (`firstKnownPrice()` del historial; el actual si no tiene historial), saca cantidad, mediana, promedio, p25 y p75 por moneda, para toda la Comarca y por ciudad. `Support/Distribution` hace las cuentas. |
| Rangos de superficie | Casas (m² cubiertos) y terrenos (m² del lote) también guardan estadísticas por rango (`price_stats.size_range`, clave de `SizeRange` como `0-1000`; null = todas las superficies), para comparar lugares con avisos parecidos. Cortes en `config('market.size_ranges')`, declarados por `PriceSeries::sizeRanges()`. Al cambiar los cortes: `market:snapshot --backfill`. |
| Foto mensual | `Services/MarketSnapshot` + `market:snapshot`: guarda en `price_stats` (por serie, moneda, ciudad, rango y dormitorios) el mes pasado y el mes en curso (todos los martes a las 04:00; el mes en curso se ve como "(en curso)" hasta que termina), reemplazando lo que hubiera de esos meses. `--month=YYYY-MM` calcula uno; `--backfill` toda la historia hasta el mes en curso. |
| Gráficos | `Queries/PriceHistory` arma los puntos; `resources/views/market` + Chart.js (`resources/js/market-charts.js`). Cada gráfico tiene una línea de mediana por lugar (`PriceHistory::places()`: Toda la Comarca + cada ciudad con datos, color fijo por lugar); un selector arriba (chips para sacar, buscador para agregar) aplica a todos y se guarda en `?places=`. La banda p25–p75 solo se ve con un único lugar. Cada gráfico con rangos tiene sus chips ("Todas" por defecto, en `?size[<serie>]=`). |

**Hoy:** `app/Domains/Market/`. Para sumar un gráfico: una clase que implemente `PriceSeries` y agregarla en `config/market.php`; `MarketSnapshot` y la página la toman solos.

## Locations

**Qué resuelve:** las ciudades de la Comarca Andina y cómo reconocerlas en el texto de un aviso. Lo comparten Rentals, Lands y Houses para filtrar por ciudad sin depender uno del otro.

| Subdominio | Descripción |
|---|---|
| Ciudades | `Enums/City` (El Bolsón, Lago Puelo, El Hoyo, Epuyén, Cholila): etiqueta, alias (`City::aliases()`) y barrios conocidos (`City::neighborhoods()`) de cada una. |
| Reconocimiento | `Support/CityResolver` → `Data/ResolvedLocation`: ciudad y barrio a partir de los textos del aviso (alias + barrios conocidos, basado en evidencia, nunca adivina). |

**Hoy:** `app/Domains/Locations/`. Para reconocer una ciudad, barrio o tipo de propiedad nuevo: agregarlo en `City::aliases()` / `City::neighborhoods()` / `PropertyUse::keywords()` y correr `rentals:classify`, `lands:classify` y `houses:classify` (sin IA, sin costo).

## Subscriptions

**Qué resuelve:** avisarle a cada persona solo los alquileres que le sirven, por el canal que prefiera.

| Subdominio | Descripción |
|---|---|
| Suscriptores | Quién recibe alertas: un `Subscriber` por Member (`user_id`), con una o varias suscripciones push (una por navegador). Las suscripciones viejas, anteriores a esto, no tienen Member (`user_id` null). |
| Vencimiento | Cada `Subscriber` tiene `expires_at`: solo los activos (`Subscriber::active()`) reciben alertas, ven el botón "Activar notificaciones" y ven los últimos alquileres. Registrarse suscribe por un período (listener de `Registered` en `AppServiceProvider`); `Subscriber::extend()` renueva desde el vencimiento actual (o desde ahora si ya venció); a mano con `subscriptions:extend <teléfono> [--days=]`. Sin suscripción activa (salvo admins) el listado solo muestra los alquileres agregados hace `free_delay_days` o más (`RentalSearch::$addedBefore`, lo decide `RentalController`). Reglas en `docs/business/strategy.md`, números en `config/subscriptions.php`. |
| Aviso de vencimiento | `subscriptions:remind-expiring` (diario) → `ExpiryReminder`: una vez por período, solo a quien abrió la app en los últimos `active_within_days` (`users.last_seen_at`, lo pone el middleware web `TrackLastSeen` como mucho una vez por hora). |
| Preferencias y matching | (Futuro) Qué le interesa a cada uno (zona, precio, gas natural...) y qué avisos nuevos le corresponden. |
| Canales | Cómo se envía: web push y Telegram (hoy), WhatsApp (Twilio/Kapso, apagado). |
| Envíos | `NewRentalsNotifier` arma y envía la notificación ("N alquileres nuevos") a cada suscriptor activo. |

**Hoy:** `app/Domains/Subscriptions/`:

| Clase | Subdominio |
|---|---|
| `Models/Subscriber` (alias `subscriber` en el morph map) | Suscriptores y vencimiento |
| `Services/NewRentalsNotifier`, `Notifications/NewRentalsNotification` | Envíos (cada suscriptor recibe por los canales que tiene: `via()`) |
| `Services/ExpiryReminder` | Aviso de vencimiento |
| `Channels/TelegramChannel` + `App\Integrations\Telegram\TelegramClient` | Canal Telegram (ver abajo) |
| `Contracts/WhatsappSender`, `Services/TwilioWhatsappSender`, `Services/KapsoWhatsappSender`, `Models/WhatsappSubscriber` | Canales (WhatsApp, apagado) |

**Web push:** el alta y la baja están en `app/Http/Controllers/PushSubscriptionController` (rutas web `push-subscriptions.store/destroy` bajo `auth`, necesitan sesión + CSRF; no hay rutas de API). Del lado del navegador, `resources/js/push.js` (clave VAPID y URL de los `<meta>` de `layouts/app.blade.php`): "Activar notificaciones" en el listado solo aparece si este navegador no está suscripto (`pushManager.getSubscription()` con la clave VAPID actual; si ya hay una, se reenvía para que el servidor la conserve). Desactivar está en el perfil ("Notificaciones en este dispositivo", solo si está suscripto). Si un navegador lo suscribe otro Member, la suscripción pasa al último.

**Telegram:** el Member conecta su chat desde el perfil (`t.me/<bot>?start=<telegram_link_token>` → `TelegramWebhookController` en `POST /telegram/webhook`, validado con `TELEGRAM_WEBHOOK_SECRET`, guarda el `chat_id`) y lo prende o apaga con el checkbox "Recibir avisos por Telegram" (`subscribers.telegram_enabled`, prendido por defecto). Si bloquea el bot, se desconecta solo. Sin `TELEGRAM_BOT_TOKEN` el canal y la sección del perfil quedan apagados.
- Correr `telegram:set-webhook` cada vez que cambia `APP_URL` (`switch-url.sh ngrok` lo hace solo).
- Local usa su propio bot de desarrollo, nunca el de prod (`TechitoBot`): un bot tiene un solo webhook, así que fuera de producción el comando se niega a pisar un webhook que no sea de ngrok (`--force` para forzarlo).
- Los admins registran Members desde su chat vinculado con `/registrar <teléfono>` (otros chats se ignoran), ver [Members](#members).

Al borrar la cuenta se borran el suscriptor y sus suscripciones push (listener `User::deleting` en `AppServiceProvider`).

## Members

**Qué resuelve:** la identidad de las personas que usan la app.

| Subdominio | Descripción |
|---|---|
| Registro y acceso | Alta y login con teléfono y contraseña. `Actions/RegisterMember` lo comparten el registro web y el comando `/registrar` del bot (clave `members.initial_password`); dispara `Registered`, así queda suscripto. |
| Admins | `ADMIN_PHONES` (`config('app.admin_phones')`, `User::isAdmin()`) respalda el Gate `admin`: las pestañas Terrenos, Casas y Análisis usan `@can('admin')` y sus rutas `can:admin` (403 para el resto). |
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

**Hoy:** no existe todavía; se crea con el checkout.

---

## Transversal

### Actualizaciones de la PWA

La versión (`App\Support\AppVersion`: `APP_VERSION` o el hash corto del commit deployado) se ve chiquita abajo a la derecha (`<x-app-version />`). Cuando la app instalada vuelve al frente consulta `GET /version` y se recarga si cambió (script en `layouts/app.blade.php`), así un deploy llega sin reinstalar. Solo los cambios del manifest (nombre, ícono) necesitan reinstalar.

### Idioma

Código, nombres y comentarios en inglés; textos para el usuario en español. El locale es `es`: los textos de Breeze escritos en inglés (`__('Profile')`) se traducen en `lang/es.json` y los del framework (validación, auth, contraseñas) en `lang/es/*.php`.

---

## Decisiones de arquitectura (ADR)

Cada decisión de diseño que no sea obvia se registra acá como un ADR (*Architecture Decision Record*), con su análisis de trade-offs: no hay mejores prácticas, solo trade-offs. Formato:

```
### ADR-NNN — Título (YYYY-MM-DD)
- Contexto: qué problema había y qué fuerzas estaban en juego.
- Decisión: qué se eligió.
- Alternativas: qué otras opciones se evaluaron y por qué no.
- Consecuencias: qué se gana y qué se resigna.
```

Un ADR no se edita cuando cambia la decisión: se agrega uno nuevo que lo reemplaza ("Reemplaza a ADR-NNN").

### ADR-001 — Ciudad, barrio y uso se clasifican sin IA
- Contexto: cada aviso trae la ubicación y el tipo de propiedad como texto libre de la inmobiliaria, y los filtros necesitan valores fijos.
- Decisión: resolverlos con alias y barrios conocidos (`CityResolver`, `PropertyUseResolver`), basados en evidencia; la IA solo como respaldo cuando el dato está en la página de detalle.
- Alternativas: pedirle todo a la IA en la extracción.
- Consecuencias: reclasificar toda la base es instantáneo y gratis (`*:classify`) y nunca se adivina; a cambio, cada ciudad, barrio o tipo nuevo hay que agregarlo a mano en `City` / `PropertyUse`.

### ADR-002 — Market mide los avisos por mes de publicación y guarda una foto mensual
- Contexto: los gráficos de Análisis necesitan precios comparables mes a mes, con avisos en pesos y en dólares.
- Decisión: cada mes cuenta los avisos **publicados** en ese mes (`created_at`), convertidos con el dólar promedio del mes, y el resultado se guarda en `price_stats` (tabla propia de Market).
- Alternativas: medir los avisos activos en cada mes, o calcular en vivo sobre las tablas de los catálogos.
- Consecuencias: la historia se puede reconstruir en cualquier momento (`--backfill`) y la página no hace cálculos pesados; a cambio, un aviso que sigue publicado varios meses solo cuenta en el mes en que apareció, y los cambios de criterio necesitan un backfill.

### ADR-003 — El catálogo guarda el último precio y cada catálogo tiene su historial (2026-11-24)
- Contexto: el importer solo reconocía los avisos por URL y nunca actualizaba el precio, así que las bajas se perdían, y si la inmobiliaria cambiaba la URL (ej. un slug con otro título) el mismo aviso se guardaba y se avisaba como nuevo. Market agrupa por mes de publicación (ADR-002): si el aviso pasa a tener el último precio, un alquiler de marzo que sube en junio movería la mediana de marzo (con la inflación en pesos pasa siempre) y cada backfill reescribiría el pasado.
- Decisión: `rentals`, `lands` y `houses` siempre tienen el último precio (lo que ven el listado, los filtros y los avisos). Cada catálogo tiene su tabla de historial (`rental_price_changes`, `land_price_changes`, `house_price_changes`), escrita por Harvesting a través del modelo del dominio, con una fila inicial por aviso, una por cambio real y el método y la confianza del match. Para reconocer un aviso: URL y después `external_id` (id de la inmobiliaria en el link, regex por fuente) dentro de la misma fuente. Market cuenta cada aviso en su mes de publicación con el primer precio conocido.
- Alternativas: una tabla polimórfica compartida (no tendría un dominio dueño); guardar solo el último precio y dejar Market sobre el catálogo (distorsiona los meses pasados); matching aproximado (huella, foto, texto) desde el principio (más caro y riesgoso; queda para después con `match_confidence` < 1); medir en Market el precio vigente a fin de cada mes (necesita meses de historial; es el paso siguiente).
- Consecuencias: se ven las bajas de precio, un cambio de URL en sitios con id ya no genera un aviso nuevo y los meses pasados de Análisis quedan fijos; a cambio, hay tres tablas casi iguales, los sitios con solo slug (Tornero, Di Salvo, Ester Cortez) siguen dependiendo de la URL, y Market todavía no refleja las subas y bajas posteriores a la publicación.

### ADR-004 — Un precio nuevo se toma recién cuando dos lecturas coinciden (2026-11-24)
- Contexto: con el historial de ADR-003, si la IA lee mal un precio en una corrida (un cero de más, el precio de otro aviso), queda registrado como cambio y la tarjeta muestra una baja falsa. La IA no es estable con la misma captura (ver listados largos de Di Salvo).
- Decisión: un precio distinto queda pendiente (`pending_price*` en el catálogo) y se confirma solo si la lectura siguiente da lo mismo. Mientras haya pendientes en una fuente no se guarda su snapshot, para que la próxima corrida la vuelva a leer aunque la página no haya cambiado. El tope de `max_ratio` queda como red de seguridad.
- Alternativas: comparar contra el precio de una semana antes (pierde cambios dentro de la semana y no frena un error en el día de referencia); tomar "la página no cambió" como confirmación (el mismo HTML no confirma que la IA lo haya leído bien); verificar que el monto esté escrito en el HTML capturado (sin demora, pero depende del formato de cada sitio; se puede sumar después).
- Consecuencias: un error aislado de la IA no llega al historial ni a la tarjeta; a cambio, cada cambio de precio tarda una corrida más (un día en alquileres, una semana en terrenos y casas) y cuesta una lectura extra de la IA por fuente con pendientes.

### ADR-005 — Un aviso que reaparece después de 30 días es un aviso nuevo (2026-11-24)
- Contexto: al cargar `external_id` aparecieron alquileres guardados dos veces con el mismo id: uno inactivo de 2025-09 y otro de 2026-11 con otro título, precio y hasta otra cantidad de dormitorios. No se puede saber si es la misma casa editada o un aviso reusado para otra propiedad (el link viejo muestra lo mismo que el nuevo). Además el importer reactivaba cualquier aviso que volviera, por URL o por id, aunque hubiera estado despublicado un año: no se avisaba y Market lo contaba en el mes original.
- Decisión: solo se reconocen avisos activos o desactivados hace menos de `harvesting.relisting_after_days` (30) días, según `deactivated_at`. Uno que vuelve después se guarda y se avisa como nuevo. `source` deja de ser único (índice común) porque la misma URL puede volver como aviso nuevo.
- Alternativas: reactivar siempre (como antes: se pierde la disponibilidad nueva y se distorsiona Market); comparar los datos del aviso para detectar reúsos (otra ciudad, otros ambientes): queda para la etapa 4, con `match_confidence`; mantener `source` único y archivar la fila vieja cambiándole la URL (ensucia el dato).
- Consecuencias: una propiedad que vuelve al mercado se avisa y cuenta en su mes, y los avisos que la IA saltea una corrida se siguen reactivando; a cambio, la misma propiedad puede quedar en varias filas (una por cada vez que se publicó), y los inactivos viejos tomaron `updated_at` como `deactivated_at`, que puede ser más reciente que la fecha real.
