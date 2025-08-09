<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RentalService
{
    public function store($modelDataResponse, $site) {
        try {
            foreach ($modelDataResponse as $item) {
                $item['site_url'] = $site['url'];

//                    $notExistentRental = !Rental::where('source', $item['Link'])->exists();
//                    if ($notExistentRental) {
                if (true) { // TODO: desactivar guardar en DB
//                        Rental::create([
//                            'source' => $item['Link'],
//                            'content' => $item['Content'],
//                            'description' => $item['Caracteristicas'],
//                        ]);

                    Log::info('✅ Datos guardados con éxito en la DB.');

                    $allItems[] = $item; // solo nuevos
                }
            }
        } catch (\Throwable $e) {
            Log::error('❌ Error parseando o guardando JSON para: ' . $site['url'] . ' ' . $e->getMessage());
        }
    }
}
