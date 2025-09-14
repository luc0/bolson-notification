<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function index(Request $request)
    {
        // Obtener rentals de la base de datos
        $query = Rental::query();

        // Filter by date if 'date' parameter is provided in the request
        if (!$request->has('date')) {
            $rentals = $query->orderBy('created_at', 'desc')->get();
            return view('rentals.index', compact('rentals'));
        }

        $date = $request->input('date');
        
        // If it's 'today', filter by current date
        if ($date === 'today') {
            $query->whereDate('created_at', today());
            $rentals = $query->orderBy('created_at', 'desc')->get();
            return view('rentals.index', compact('rentals'));
        }

        // If it's a specific date, use that date
        try {
            $formattedDate = \Carbon\Carbon::parse($date)->format('Y-m-d');
            $query->whereDate('created_at', $formattedDate);
        } catch (\Exception $e) {
            // If date is invalid, show all rentals
        }

        $rentals = $query->orderBy('created_at', 'desc')->get();

        return view('rentals.index', compact('rentals'));
    }
}
