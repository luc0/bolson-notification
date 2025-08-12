<?php

namespace App\Services;

use App\Contracts\IScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MockScraperServiceService implements IScraperService
{
    public function __construct(private readonly AiProcessingService $aiProcessingService, private readonly RentalService $rentalService)
    {
    }

    public function scrape() {
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
