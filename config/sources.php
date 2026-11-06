<?php

/*
|--------------------------------------------------------------------------
| Rental sources
|--------------------------------------------------------------------------
|
| Single source of truth for the sites we get rentals from (Sources domain).
| To add a site, add it here only.
|
| - key (array key): identifies the source (also used for capture file names).
| - type: "agency" (own properties) or "portal" (aggregator), see SourceType.
| - listing_url: page with the rental listings that gets scraped.
| - link_base_url: base to complete relative listing links returned by the AI.
| - wait_for: CSS selector to wait for on sites that render listings with JS.
|
*/

return [

    'sites' => [

        'anezca' => [
            'name' => 'Anezca Propiedades',
            'type' => 'agency',
            'listing_url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
            'link_base_url' => 'https://anezcapropiedades.com.ar/propiedades',
            'wait_for' => null,
        ],

        'puntopatagonia' => [
            'name' => 'Punto Patagonia',
            'type' => 'agency',
            'listing_url' => 'https://www.inmobiliariapuntopatagonia.com.ar/Alquiler',
            // e.g. https://www.inmobiliariapuntopatagonia.com.ar/p/6858071-Departamento-en-Alquiler-en-El-Bolson-...
            'link_base_url' => 'https://www.inmobiliariapuntopatagonia.com.ar/p/',
            'wait_for' => '.prop-desc',
        ],

        'puntosurpropiedades' => [
            'name' => 'Punto Sur Propiedades',
            'type' => 'agency',
            'listing_url' => 'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=1&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=2#listado',
            // e.g. https://puntosurpropiedades.ar/web/propiedad.php?id_propiedad=655
            'link_base_url' => 'https://puntosurpropiedades.ar/web/',
            'wait_for' => null,
        ],

        'rioazulpropiedades' => [
            'name' => 'Río Azul Propiedades',
            'type' => 'agency',
            'listing_url' => 'https://www.rioazulpropiedades.com/Buscar?operation=2&locations=40933&o=2,2&1=1',
            // e.g. https://www.rioazulpropiedades.com/p/6667459-Departamento-en-Alquiler-en-Centro-rivadavia
            'link_base_url' => 'https://www.rioazulpropiedades.com/',
            'wait_for' => null,
        ],

        'inmobiliariadelagua' => [
            'name' => 'Inmobiliaria Del Agua',
            'type' => 'agency',
            'listing_url' => 'https://inmobiliariadelagua.com.ar/s/alquiler////?business_type%5B%5D=for_rent',
            // e.g. https://inmobiliariadelagua.com.ar/departamento-alquiler-casco-centrico-el-bolson/8587691
            'link_base_url' => 'https://inmobiliariadelagua.com.ar',
            'wait_for' => '.item',
        ],

    ],

];
