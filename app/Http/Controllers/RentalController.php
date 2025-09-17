<?php

namespace App\Http\Controllers;

use App\Location;
use App\Models\Rental;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function index(Request $request)
    {
        // Obtener rentals de la base de datos (solo los activos)
        $query = Rental::where('active', true);

        // Filter by location if 'locations' parameter is provided
        if ($request->has('locations') && !empty($request->input('locations'))) {
            $locations = $request->input('locations');
            // Filtrar por ubicaciones (tanto del enum como personalizadas)
            $query->whereIn('location', $locations);
        }

        // Filter by date if 'date' parameter is provided in the request
        if (!$request->has('date')) {
            $rentals = $query->orderBy('created_at', 'desc')->get();
            $locationOptions = $this->getLocationOptions();
            return view('rentals.index', compact('rentals', 'locationOptions'));
        }

        $date = $request->input('date');
        
        // If it's 'today', filter by current date
        if ($date === 'today') {
            $query->whereDate('created_at', today());
            $rentals = $query->orderBy('created_at', 'desc')->get();
            $locationOptions = $this->getLocationOptions();
            return view('rentals.index', compact('rentals', 'locationOptions'));
        }

        // If it's a specific date, use that date
        try {
            $formattedDate = \Carbon\Carbon::parse($date)->format('Y-m-d');
            $query->whereDate('created_at', $formattedDate);
        } catch (\Exception $e) {
            // If date is invalid, show all active rentals
        }

        $rentals = $query->orderBy('created_at', 'desc')->get();
        $locationOptions = $this->getLocationOptions();

        return view('rentals.index', compact('rentals', 'locationOptions'));
    }

    private function getLocationOptions(): array
    {
        // Obtener todas las ubicaciones disponibles (enum + personalizadas de la DB)
        $enumLocations = Location::options();
        $customLocations = Rental::where('active', true)
            ->whereNotNull('location')
            ->whereNotIn('location', Location::values())
            ->distinct()
            ->pluck('location', 'location')
            ->toArray();
        
        return array_merge($enumLocations, $customLocations);
    }
}
