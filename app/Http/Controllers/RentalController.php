<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function index()
    {
        // Obtener rentals de la base de datos
        $rentals = Rental::all();

        return view('rentals.index', compact('rentals'));
    }
}
