<?php

namespace App\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ScraperService
{
    public function __construct(private readonly AiProcessingService $aiProcessingService, private readonly RentalService $rentalService)
    {
    }

    public function scrape() {
        // Scrapear.
        $process = new Process(['node', base_path('scrape.cjs')]);
        $process->setTimeout(120); // 2 minutos como máximo

        try {
            $process->mustRun();
            Log::info("✅ Scrap done.", ['timestamp' => now()]);
        } catch (ProcessFailedException $e) {
            Log::error("❌ Error al ejecutar el script de scrapeo:");
            Log::error($e->getMessage());
            return Command::FAILURE;
        }

        // ANALYZE
        $sites = [
            [
                'html' => base_path('captures/anezca.html'), // TODO: OK!
                'url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
                'postUrl' => 'https://anezcapropiedades.com.ar/propiedades' // No hace falta
            ],
            [
                'html' => base_path('captures/puntopatagonia.html'), // OK (antes: no obtiene link de la publicacion. ver html) (funca oiP https://www.inmobiliariapuntopatagonia.com.ar/p/6858071-Departamento-en-Alquiler-en-El-Bolson-El-Bols%C3%B3n,-departamento-a-200-mts-de-plaza-Pagano)
                'url' => 'https://www.inmobiliariapuntopatagonia.com.ar/Alquiler',
                'postUrl' => 'https://www.inmobiliariapuntopatagonia.com.ar/p/'
            ],
            [
                'html' => base_path('captures/puntosurpropiedades.html'), // TODO: OK (antes: no obtiene link de la publicacion. ver html) (funco con: https://puntosurpropiedades.ar/web/propiedad.php?id_propiedad=655)
                'url' => 'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=1&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=2#listado',
                'postUrl' => 'https://puntosurpropiedades.ar/web/'
            ],
            [
                'html' => base_path('captures/rioazulpropiedades.html'), // TODO: OK! (antes redirigia a la home) (funco con: https://www.rioazulpropiedades.com/p/6667459-Departamento-en-Alquiler-en-Centro-rivadavia)
                'url' => 'https://www.rioazulpropiedades.com/Buscar?operation=2&locations=40933&o=2,2&1=1',
                'postUrl' => 'https://www.rioazulpropiedades.com/'
            ],
            [
                'html' => base_path('captures/inmobiliariadelagua.html'), // TODO: OK! (antes no funcaba) (funco con: https://inmobiliariadelagua.com.ar/departamento-alquiler-casco-centrico-el-bolson/8587691)
                'url' => 'https://inmobiliariadelagua.com.ar/s/alquiler////?business_type%5B%5D=for_rent',
                'postUrl' => 'https://inmobiliariadelagua.com.ar'
            ]
        ];

        $allItems = [];

        foreach ($sites as $site) {
            if (!file_exists($site['html'])) {
                Log::error('No se encontró el archivo HTML: ' . $site['html']);
                continue;
            }

            // Check site snapshot updates
            if (!File::exists(storage_path('html_snapshots'))) {
                File::makeDirectory(storage_path('html_snapshots'));
            }

            $html = file_get_contents($site['html']);

            $snapshotPath = storage_path('html_snapshots/' . basename($site['html']));

            if (File::exists($snapshotPath)) {
                $previousHtml = file_get_contents($snapshotPath);

                if (hash('sha256', $html) === hash('sha256', $previousHtml)) {
                    Log::info('🔁 Sin cambios detectados en el HTML de: ' . $site['url']);
                    continue;
                }
            }

            // Guardamos el nuevo snapshot para futuras comparaciones
            file_put_contents($snapshotPath, $html);

            // Continua con el analyze.

            Log::info('📤 Enviando HTML a DeepSeek para analizar: ' . $site['url']);

            $html = file_get_contents($site['html']);

            /*
             * Models:
             * meta-llama/llama-4-scout-17b-16e-instruct (precio 0.1/0.35) (parseo, mas nuevo, emojis) [tokens: ~3.1k] [latencia: ~1s]
             * llama-3.1-8b-instant (precio 0.05/0.05) (parseo simple, no emojis) [tokens ~3.6k] [latencia: ~1s]
             * qwen-qwq-32b (precio 0.3/0.4) (razona, pero revuelve think y es un monton) [tokens 7k] [latencia: ~9s]
             */
            $modelDataResponse = $this->aiProcessingService->process($site, $html);

//            Log::info(json_encode($modelDataResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->rentalService->store($modelDataResponse, $site);
        }

        return $allItems;
    }
}
