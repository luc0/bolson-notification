<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Harvesting\Contracts\RentalScraper;
use Illuminate\Support\Facades\Log;

class FakeRentalScraper implements RentalScraper
{
    public function __construct(private readonly ListingExtractor $listingExtractor, private readonly RentalImporter $rentalImporter) {}

    public function scrape(): array
    {
        Log::info('Utilizando Mock de scrapeo');
        $data = [
            [
                'Content' => 'IMPERDIBLE MONOAMBIENTE EN ALQUILER',
                'Link' => 'https://anezcapropiedades.com.ar/propiedades/73/departamento-en-alquiler-en-el-bolson',
                'Caracteristicas' => '🏠 ubicación: El Bolsón\n💸 precio: U$S550\n🌐 ambientes: 1\n🚿 baños: 0\n📏 superficie: 30m²',
                'site_url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
            ],
            [
                'Content' => 'SE ALQUILA DEPARTAMENTO DE DOS DORMITORIOS, AMUEBLADO, CON EXCELENTE UBICACIÓN !',
                'Link' => 'https://anezcapropiedades.com.ar/propiedades/69/departamento-en-alquiler-en-el-bolson',
                'Caracteristicas' => '🏠 ubicación: El Bolsón\n💸 precio: U$S950\n🌐 ambientes: 2\n🚿 baños: 2\n📏 superficie: 74m²',
                'site_url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
            ],
            [
                'Content' => 'SE ALQUILA MONOAMBIENTE AMUEBLADO. EXCELENTE UBICACIÓN !',
                'Link' => 'https://anezcapropiedades.com.ar/propiedades/68/departamento-en-alquiler-en-el-bolson',
                'Caracteristicas' => '🏠 ubicación: El Bolsón\n💸 precio: U$S450\n🌐 ambientes: 1\n🚿 baños: 0\n📏 superficie: 30m²',
                'site_url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
            ],
        ];

        return $data;
    }
}
