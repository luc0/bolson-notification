<?php

/*
|--------------------------------------------------------------------------
| Sources
|--------------------------------------------------------------------------
|
| Single source of truth for the sites we scrape (Sources domain).
| To add a site, add it here only.
|
| - rental_sites: rental listings (Rentals), read with Source::rentals().
| - land_sites: lands for sale (Lands), read with Source::lands(). Same keys as `rental_sites` are fine:
|   land captures are stored in their own folder (config/harvesting.php).
| - house_sites: houses for sale (Houses), read with Source::houses(). Own capture folder too.
|
| - key (array key): identifies the source (also used for capture file names).
| - type: "agency" (own properties) or "portal" (aggregator), see SourceType.
| - listing_url: page with the rental listings that gets scraped.
| - link_base_url: base to complete relative listing links returned by the AI.
| - wait_for: CSS selector to wait for on sites that render listings with JS.
| - default_city: City enum value, only for sources whose listing_url is filtered by city.
|   Used when a rental's texts don't name its city.
| - next_page_selector: CSS selector of the "next page" link on paginated listings. The scraper follows it
|   (up to 5 pages) and merges them into one capture, so rentals on later pages aren't deactivated.
|   Sites that open listings from an onclick (no href) work too: scrape.cjs keeps that URL as an href.
| - listing_id_pattern: regex whose first group is the agency's own id of a listing in its link. Lets the importer
|   recognize a listing whose URL changed (e.g. a new title in the slug) instead of storing it as new.
|   Only for sites with a stable id in the URL; slug-only sites (Tornero, Di Salvo, Ester Cortez) leave it out.
|
*/

return [

    'rental_sites' => [

        'anezca' => [
            'name' => 'Anezca Propiedades',
            'type' => 'agency',
            'listing_url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
            'link_base_url' => 'https://anezcapropiedades.com.ar/propiedades',
            'wait_for' => null,
            'default_city' => 'el_bolson', // listing filtered by location_id=1396
        ],

        'puntopatagonia' => [
            'name' => 'Punto Patagonia',
            'type' => 'agency',
            'listing_url' => 'https://www.inmobiliariapuntopatagonia.com.ar/Alquiler',
            // e.g. https://www.inmobiliariapuntopatagonia.com.ar/p/6858071-Departamento-en-Alquiler-en-El-Bolson-...
            'link_base_url' => 'https://www.inmobiliariapuntopatagonia.com.ar/p/',
            'listing_id_pattern' => '#/p/(\d+)-#',
            'wait_for' => '.prop-desc',
            'default_city' => null, // listing mixes cities
        ],

        'puntosurpropiedades' => [
            'name' => 'Punto Sur Propiedades',
            'type' => 'agency',
            'listing_url' => 'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=1&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=2#listado',
            // e.g. https://puntosurpropiedades.ar/web/propiedad.php?id_propiedad=655
            'link_base_url' => 'https://puntosurpropiedades.ar/web/',
            'listing_id_pattern' => '#[?&]id_propiedad=(\d+)#',
            'wait_for' => null,
            'default_city' => 'el_bolson', // listing filtered by search_locality
        ],

        'rioazulpropiedades' => [
            'name' => 'Río Azul Propiedades',
            'type' => 'agency',
            'listing_url' => 'https://www.rioazulpropiedades.com/Buscar?operation=2&locations=40933&o=2,2&1=1',
            // e.g. https://www.rioazulpropiedades.com/p/6667459-Departamento-en-Alquiler-en-Centro-rivadavia
            'link_base_url' => 'https://www.rioazulpropiedades.com/',
            'listing_id_pattern' => '#/p/(\d+)-#',
            'wait_for' => null,
            'default_city' => 'el_bolson', // listing filtered by locations=40933
        ],

        'inmobiliariadelagua' => [
            'name' => 'Inmobiliaria Del Agua',
            'type' => 'agency',
            'listing_url' => 'https://inmobiliariadelagua.com.ar/s/alquiler////?business_type%5B%5D=for_rent',
            // e.g. https://inmobiliariadelagua.com.ar/departamento-alquiler-casco-centrico-el-bolson/8587691
            'link_base_url' => 'https://inmobiliariadelagua.com.ar',
            'listing_id_pattern' => '#/(\d+)/?$#',
            'wait_for' => '.item',
            'default_city' => null, // listing mixes cities
        ],

        'surpatagonian' => [
            'name' => 'Sur Patagonian',
            'type' => 'agency',
            // Listings rendered with JS (Supabase); each card opens the listing from an onclick.
            'listing_url' => 'https://surpatagonian.com/propiedades?op=alquiler',
            // e.g. https://surpatagonian.com/propiedad.html?id=b0d9b5b5-a0ab-4c34-b5da-be4186ea2f69
            'link_base_url' => 'https://surpatagonian.com/',
            'listing_id_pattern' => '#[?&]id=([0-9a-f-]{36})#i',
            'wait_for' => '.card-propiedad',
            'default_city' => null, // the site covers the whole Comarca
        ],

        'tornero' => [
            'name' => 'Tornero Inmobiliaria',
            'type' => 'agency',
            'listing_url' => 'https://www.torneroinmobiliaria.com.ar/categoria/alquileres/',
            // e.g. https://www.torneroinmobiliaria.com.ar/propiedad/depto-centrico/
            'link_base_url' => 'https://www.torneroinmobiliaria.com.ar',
            'wait_for' => null,
            'default_city' => null, // listing mixes cities (e.g. Las Golondrinas)
            'next_page_selector' => 'a.next.page-numbers', // WordPress pagination, 5 per page
        ],

    ],

    'land_sites' => [

        'anezca' => [
            'name' => 'Anezca Propiedades',
            'type' => 'agency',
            // property_type_id=5 (Terreno), operation_id=1 (Venta), every location of the Comarca.
            'listing_url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=5&operation_id=1&location_id=&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
            'link_base_url' => 'https://anezcapropiedades.com.ar/propiedades',
            'wait_for' => null,
        ],

        'puntopatagonia' => [
            'name' => 'Punto Patagonia',
            'type' => 'agency',
            'listing_url' => 'https://www.inmobiliariapuntopatagonia.com.ar/Terrenos',
            // e.g. https://www.inmobiliariapuntopatagonia.com.ar/p/5399685-Terreno-en-Venta-en-Lago-Puelo-...
            'link_base_url' => 'https://www.inmobiliariapuntopatagonia.com.ar/p/',
            'listing_id_pattern' => '#/p/(\d+)-#',
            'wait_for' => '.prop-desc',
        ],

        'puntosurpropiedades' => [
            'name' => 'Punto Sur Propiedades',
            'type' => 'agency',
            // search_tipo_de_propiedad=8 (Terreno), search_tipo_de_operacion=1 (Venta).
            'listing_url' => 'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=8&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=1#listado',
            'link_base_url' => 'https://puntosurpropiedades.ar/web/',
            'listing_id_pattern' => '#[?&]id_propiedad=(\d+)#',
            'wait_for' => null,
            'default_city' => 'el_bolson', // listing filtered by search_locality
        ],

        'rioazulpropiedades' => [
            'name' => 'Río Azul Propiedades',
            'type' => 'agency',
            'listing_url' => 'https://www.rioazulpropiedades.com/Terrenos',
            'link_base_url' => 'https://www.rioazulpropiedades.com/',
            'listing_id_pattern' => '#/p/(\d+)-#',
            'wait_for' => null,
        ],

        'inmobiliariadelagua' => [
            'name' => 'Inmobiliaria Del Agua',
            'type' => 'agency',
            'listing_url' => 'https://inmobiliariadelagua.com.ar/s/lote-terreno/venta?id_property_type=5&business_type%5B0%5D=for_sale',
            // e.g. https://inmobiliariadelagua.com.ar/lote-terreno-venta-cerro-amigo-el-bolson/10349652
            'link_base_url' => 'https://inmobiliariadelagua.com.ar',
            'listing_id_pattern' => '#/(\d+)/?$#',
            'wait_for' => '.item',
        ],

        'ricardorubio' => [
            'name' => 'Ricardo Rubio Inmobiliaria',
            'type' => 'agency',
            // Same site template as rioazul / puntopatagonia: shows the first 40 lots (loads more on scroll).
            'listing_url' => 'https://www.ricardorubiopatagonia.com/Terrenos',
            // e.g. https://www.ricardorubiopatagonia.com/p/3913442-Terreno-en-Venta-en-Las-Golondrinas
            'link_base_url' => 'https://www.ricardorubiopatagonia.com/',
            'listing_id_pattern' => '#/p/(\d+)-#',
            'wait_for' => null,
        ],

        'actitud' => [
            'name' => 'Actitud Inmobiliaria',
            'type' => 'agency',
            // Same site template as rioazul / puntopatagonia: shows the first 40 lots (loads more on scroll).
            'listing_url' => 'https://www.actitudinmobiliaria.com.ar/Terrenos',
            // e.g. https://www.actitudinmobiliaria.com.ar/p/13741797-Terreno-en-Venta-en-El-Bolson-Terreno-en-barrio-Los-Abedules
            'link_base_url' => 'https://www.actitudinmobiliaria.com.ar/',
            'listing_id_pattern' => '#/p/(\d+)-#',
            'wait_for' => null,
        ],

        'alcalde' => [
            'name' => 'Alcalde Inmobiliaria',
            'type' => 'agency',
            'listing_url' => 'https://www.alcaldeinmobiliaria.com.ar/venta/lotes',
            // e.g. https://www.alcaldeinmobiliaria.com.ar/propiedad/venta-el-bolson-lote-en-venta-buena-vista-190
            'link_base_url' => 'https://www.alcaldeinmobiliaria.com.ar',
            'wait_for' => null,
        ],

        'disalvo_bolson' => [
            'name' => 'Di Salvo Propiedades',
            'type' => 'agency',
            // Lots for sale of El Bolsón (property_location=62), 10 per page. One source per city: the whole
            // list (50+) is too long for the AI, which then returns a different subset on every run.
            'listing_url' => 'https://www.disalvopropiedades.com.ar/?action=epl_search&property_location=62&tax_feature=comprar&post_type=land',
            // e.g. https://www.disalvopropiedades.com.ar/lotes/lote-en-venta-barrio-anden-el-bolson/
            'link_base_url' => 'https://www.disalvopropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'el_bolson', // listing filtered by property_location
            'next_page_selector' => '.alignright a',
        ],

        'disalvo_puelo' => [
            'name' => 'Di Salvo Propiedades',
            'type' => 'agency',
            // Lots for sale of Lago Puelo (property_location=107), 10 per page. One source per city: the whole
            // list (50+) is too long for the AI, which then returns a different subset on every run.
            'listing_url' => 'https://www.disalvopropiedades.com.ar/?action=epl_search&property_location=107&tax_feature=comprar&post_type=land',
            // e.g. https://www.disalvopropiedades.com.ar/lotes/lote-en-venta-barrio-anden-el-bolson/
            'link_base_url' => 'https://www.disalvopropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'lago_puelo', // listing filtered by property_location
            'next_page_selector' => '.alignright a',
        ],

        'disalvo_hoyo' => [
            'name' => 'Di Salvo Propiedades',
            'type' => 'agency',
            // Lots for sale of El Hoyo (property_location=108), 10 per page. One source per city: the whole
            // list (50+) is too long for the AI, which then returns a different subset on every run.
            'listing_url' => 'https://www.disalvopropiedades.com.ar/?action=epl_search&property_location=108&tax_feature=comprar&post_type=land',
            // e.g. https://www.disalvopropiedades.com.ar/lotes/lote-en-venta-barrio-anden-el-bolson/
            'link_base_url' => 'https://www.disalvopropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'el_hoyo', // listing filtered by property_location
            'next_page_selector' => '.alignright a',
        ],

        'estercortez_bolson' => [
            'name' => 'Ester Cortez Propiedades',
            'type' => 'agency',
            // WordPress category with the lots of El Bolsón only.
            'listing_url' => 'https://www.estercortezpropiedades.com.ar/category/propiedades-en-el-bolson/lotes-en-venta-en-el-bolson/',
            // e.g. https://www.estercortezpropiedades.com.ar/propiedades-bolson-puelo-hoyo/lote-en-venta-en-barrio-las-mutisias-el-bolson/
            'link_base_url' => 'https://www.estercortezpropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'el_bolson', // listing filtered by city
            'next_page_selector' => 'a.next.page-numbers',
        ],

        'estercortez_puelo' => [
            'name' => 'Ester Cortez Propiedades',
            'type' => 'agency',
            // WordPress category with the lots of Lago Puelo only.
            'listing_url' => 'https://www.estercortezpropiedades.com.ar/category/propiedades-en-lago-puelo/lotes-en-venta-en-lago-puelo/',
            // e.g. https://www.estercortezpropiedades.com.ar/propiedades-bolson-puelo-hoyo/lote-en-venta-en-barrio-las-mutisias-el-bolson/
            'link_base_url' => 'https://www.estercortezpropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'lago_puelo', // listing filtered by city
            'next_page_selector' => 'a.next.page-numbers',
        ],

        'estercortez_hoyo' => [
            'name' => 'Ester Cortez Propiedades',
            'type' => 'agency',
            // WordPress category with the lots of El Hoyo only.
            'listing_url' => 'https://www.estercortezpropiedades.com.ar/category/propiedades-en-el-hoyo/lotes-en-venta-en-el-hoyo/',
            // e.g. https://www.estercortezpropiedades.com.ar/propiedades-bolson-puelo-hoyo/lote-en-venta-en-barrio-las-mutisias-el-bolson/
            'link_base_url' => 'https://www.estercortezpropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'el_hoyo', // listing filtered by city
            'next_page_selector' => 'a.next.page-numbers',
        ],

    ],

    'house_sites' => [

        'ricardorubio' => [
            'name' => 'Ricardo Rubio Inmobiliaria',
            'type' => 'agency',
            // Same site template as rioazul / puntopatagonia: shows the first 40 houses (loads more on scroll).
            'listing_url' => 'https://www.ricardorubiopatagonia.com/Casas',
            // e.g. https://www.ricardorubiopatagonia.com/p/709133-Casa-en-Venta-en-Centro-RR3699
            'link_base_url' => 'https://www.ricardorubiopatagonia.com/',
            'listing_id_pattern' => '#/p/(\d+)-#',
            'wait_for' => null,
        ],

        'actitud' => [
            'name' => 'Actitud Inmobiliaria',
            'type' => 'agency',
            // Same site template as rioazul / puntopatagonia: shows the first 40 houses (loads more on scroll).
            'listing_url' => 'https://www.actitudinmobiliaria.com.ar/Casas',
            'link_base_url' => 'https://www.actitudinmobiliaria.com.ar/',
            'listing_id_pattern' => '#/p/(\d+)-#',
            'wait_for' => null,
        ],

        'alcalde' => [
            'name' => 'Alcalde Inmobiliaria',
            'type' => 'agency',
            'listing_url' => 'https://www.alcaldeinmobiliaria.com.ar/venta/casas',
            // e.g. https://www.alcaldeinmobiliaria.com.ar/propiedad/venta-el-bolson-casa-centrica-el-bolson-130
            'link_base_url' => 'https://www.alcaldeinmobiliaria.com.ar',
            'wait_for' => null,
        ],

        'disalvo_bolson' => [
            'name' => 'Di Salvo Propiedades',
            'type' => 'agency',
            // Houses for sale of El Bolsón (property_location=62), 10 per page. One source per city: the whole
            // list (50+) is too long for the AI, which then returns a different subset on every run.
            'listing_url' => 'https://www.disalvopropiedades.com.ar/?action=epl_search&property_location=62&tax_feature=comprar&post_type=property',
            // e.g. https://www.disalvopropiedades.com.ar/propiedades/casa-en-venta-villa-turismo-el-bolson/
            'link_base_url' => 'https://www.disalvopropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'el_bolson', // listing filtered by property_location
            'next_page_selector' => '.alignright a',
        ],

        'disalvo_puelo' => [
            'name' => 'Di Salvo Propiedades',
            'type' => 'agency',
            // Houses for sale of Lago Puelo (property_location=107), 10 per page. One source per city: the whole
            // list (50+) is too long for the AI, which then returns a different subset on every run.
            'listing_url' => 'https://www.disalvopropiedades.com.ar/?action=epl_search&property_location=107&tax_feature=comprar&post_type=property',
            // e.g. https://www.disalvopropiedades.com.ar/propiedades/casa-en-venta-villa-turismo-el-bolson/
            'link_base_url' => 'https://www.disalvopropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'lago_puelo', // listing filtered by property_location
            'next_page_selector' => '.alignright a',
        ],

        'disalvo_hoyo' => [
            'name' => 'Di Salvo Propiedades',
            'type' => 'agency',
            // Houses for sale of El Hoyo (property_location=108), 10 per page. One source per city: the whole
            // list (50+) is too long for the AI, which then returns a different subset on every run.
            'listing_url' => 'https://www.disalvopropiedades.com.ar/?action=epl_search&property_location=108&tax_feature=comprar&post_type=property',
            // e.g. https://www.disalvopropiedades.com.ar/propiedades/casa-en-venta-villa-turismo-el-bolson/
            'link_base_url' => 'https://www.disalvopropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'el_hoyo', // listing filtered by property_location
            'next_page_selector' => '.alignright a',
        ],

        'estercortez_bolson' => [
            'name' => 'Ester Cortez Propiedades',
            'type' => 'agency',
            // WordPress category with the houses of El Bolsón only.
            'listing_url' => 'https://www.estercortezpropiedades.com.ar/category/propiedades-en-el-bolson/casas-en-venta-en-el-bolson/',
            'link_base_url' => 'https://www.estercortezpropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'el_bolson', // listing filtered by city
            'next_page_selector' => 'a.next.page-numbers',
        ],

        'estercortez_puelo' => [
            'name' => 'Ester Cortez Propiedades',
            'type' => 'agency',
            // WordPress category with the houses of Lago Puelo only.
            'listing_url' => 'https://www.estercortezpropiedades.com.ar/category/propiedades-en-lago-puelo/casas-en-venta-en-lago-puelo/',
            'link_base_url' => 'https://www.estercortezpropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'lago_puelo', // listing filtered by city
            'next_page_selector' => 'a.next.page-numbers',
        ],

        'estercortez_hoyo' => [
            'name' => 'Ester Cortez Propiedades',
            'type' => 'agency',
            // WordPress category with the houses of El Hoyo only.
            'listing_url' => 'https://www.estercortezpropiedades.com.ar/category/propiedades-en-el-hoyo/casas-en-venta-en-el-hoyo/',
            'link_base_url' => 'https://www.estercortezpropiedades.com.ar',
            'wait_for' => null,
            'default_city' => 'el_hoyo', // listing filtered by city
            'next_page_selector' => 'a.next.page-numbers',
        ],

    ],

];
