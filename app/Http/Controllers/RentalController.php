<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function index()
    {
        // Datos mockup de 5 alquileres
        $rentals = [
            [
                'id' => 1,
                'source' => 'Inmobiliaria del Agua',
                'content' => 'Casa 3 dormitorios, 2 baños',
                'description' => 'Hermosa casa en el centro de El Bolsón, con jardín y parrilla. Ideal para familia. Precio: $150.000/mes',
                'price' => 150000,
                'location' => 'Centro, El Bolsón',
                'rooms' => 3,
                'bathrooms' => 2
            ],
            [
                'id' => 2,
                'source' => 'Punto Sur Propiedades',
                'content' => 'Departamento 2 dormitorios, 1 baño',
                'description' => 'Departamento moderno con vista a la montaña. Incluye cochera. Precio: $120.000/mes',
                'price' => 120000,
                'location' => 'Barrio Norte, El Bolsón',
                'rooms' => 2,
                'bathrooms' => 1
            ],
            [
                'id' => 3,
                'source' => 'Río Azul Propiedades',
                'content' => 'Casa 4 dormitorios, 3 baños',
                'description' => 'Casa amplia con quincho, pileta y jardín. Perfecta para vacaciones. Precio: $200.000/mes',
                'price' => 200000,
                'location' => 'Lago Puelo',
                'rooms' => 4,
                'bathrooms' => 3
            ],
            [
                'id' => 4,
                'source' => 'Punto Patagonia',
                'content' => 'Cabaña 1 dormitorio, 1 baño',
                'description' => 'Cabaña rústica en el bosque, ideal para parejas. Chimenea y deck. Precio: $80.000/mes',
                'price' => 80000,
                'location' => 'Mallín Ahogado',
                'rooms' => 1,
                'bathrooms' => 1
            ],
            [
                'id' => 5,
                'source' => 'Anezca Inmobiliaria',
                'content' => 'Casa 2 dormitorios, 2 baños',
                'description' => 'Casa con huerta orgánica y paneles solares. Eco-friendly. Precio: $130.000/mes',
                'price' => 130000,
                'location' => 'Villa Turismo',
                'rooms' => 2,
                'bathrooms' => 2
            ]
        ];

        return view('rentals.index', compact('rentals'));
    }
}
