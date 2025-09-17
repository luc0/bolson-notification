<?php

namespace App\Services;

use App\Models\Rental;
use Illuminate\Support\Facades\Log;

class RentalService
{
    public function store($modelDataResponse, $site, $allItems) {
        try {
            // Desactivar rentals que ya no están en el scrapeo
            $this->setUnactiveWhenNotMatchingWithScrapped($modelDataResponse, $site['url']);

            // Procesar cada item scrapeado
            foreach ($modelDataResponse as $item) {
                $item['site_url'] = $site['url'];

                $notExistentRental = !Rental::where('source', $item['Link'])->exists();
                if ($notExistentRental) {
//                if (true) { // TODO: desactivar guardar en DB
                        Rental::create([
                            'source' => $item['Link'],
                            'content' => $item['Content'],
                            'description' => $item['Caracteristicas'],
                            'price' => $item['Price'],
                            'location' => $item['Location'],
                            'rooms' => $item['Rooms'],
                            'bathrooms' => $item['Bathrooms'],
                            'site_path' => $item['site_url'],
                            'active' => true,
                        ]);

                    Log::info('✅ Datos guardados con éxito en la DB.');

                    $allItems[] = $item; // solo nuevos
                } else {
                    // Si el rental ya existe, asegurarse de que esté activo
                    Rental::where('source', $item['Link'])
                        ->update(['active' => true]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('❌ Error parseando o guardando JSON para: ' . $site['url'] . ' ' . $e->getMessage());
        }

        return $allItems;
    }

    private function getScrapedSources(array $modelDataResponse, string $siteUrl): array
    {
        $scrapedSources = [];
        foreach ($modelDataResponse as $item) {
            $item['site_url'] = $siteUrl;
            $scrapedSources[] = $item['Link'];
        }
        return $scrapedSources;
    }

    private function setUnactiveWhenNotMatchingWithScrapped(array $modelDataResponse, string $siteUrl): void
    {
        $scrapedSources = $this->getScrapedSources($modelDataResponse, $siteUrl);
        
        if (!empty($scrapedSources)) {
            Rental::where('site_path', $siteUrl)
                ->whereNotIn('source', $scrapedSources)
                ->update(['active' => false]);
            
            Log::info('🔄 Rentals desactivados para el sitio: ' . $siteUrl);
        }
    }
}
