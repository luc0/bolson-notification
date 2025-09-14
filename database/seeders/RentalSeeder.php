<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Rental;

class RentalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rentals = [
            [
                'source' => 'Inmobiliaria del Agua',
                'content' => 'Casa 3 dormitorios, 2 baños',
                'description' => 'Hermosa casa en el centro de El Bolsón, con jardín y parrilla. Ideal para familia.',
                'price' => 150000,
                'location' => 'Centro, El Bolsón',
                'rooms' => 3,
                'bathrooms' => 2,
                'source_path' => 'inmobiliariadelagua.html',
            ],
            [
                'source' => 'Punto Sur Propiedades',
                'content' => 'Departamento 2 dormitorios, 1 baño',
                'description' => 'Departamento moderno con vista a la montaña. Incluye cochera.',
                'price' => 120000,
                'location' => 'Barrio Norte, El Bolsón',
                'rooms' => 2,
                'bathrooms' => 1,
                'source_path' => 'puntosurpropiedades.html',
            ],
            [
                'source' => 'Río Azul Propiedades',
                'content' => 'Casa 4 dormitorios, 3 baños',
                'description' => 'Casa amplia con quincho, pileta y jardín. Perfecta para vacaciones.',
                'price' => 200000,
                'location' => 'Lago Puelo',
                'rooms' => 4,
                'bathrooms' => 3,
                'source_path' => 'rioazulpropiedades.html',
            ],
            [
                'source' => 'Punto Patagonia',
                'content' => 'Cabaña 1 dormitorio, 1 baño',
                'description' => 'Cabaña rústica en el bosque, ideal para parejas. Chimenea y deck.',
                'price' => 80000,
                'location' => 'Mallín Ahogado',
                'rooms' => 1,
                'bathrooms' => 1,
                'source_path' => 'puntopatagonia.html',
            ],
            [
                'source' => 'Anezca Inmobiliaria',
                'content' => 'Casa 2 dormitorios, 2 baños',
                'description' => 'Casa con huerta orgánica y paneles solares. Eco-friendly.',
                'price' => 130000,
                'location' => 'Villa Turismo',
                'rooms' => 2,
                'bathrooms' => 2,
                'source_path' => 'anezca.html',
            ]
        ];

        foreach ($rentals as $rentalData) {
            Rental::create($rentalData);
        }
    }
}
