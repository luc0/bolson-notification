<?php

use App\Domains\Rentals\Support\RentalLink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decodes percent-encoded sources and merges the duplicates this created
 * (same listing stored once encoded and once decoded).
 */
return new class extends Migration
{
    public function up(): void
    {
        $encodedRentals = DB::table('rentals')->where('source', 'like', '%\%%')->get();

        foreach ($encodedRentals as $encodedRental) {
            $normalizedSource = RentalLink::normalize($encodedRental->source);
            $duplicate = DB::table('rentals')
                ->where('source', $normalizedSource)
                ->where('id', '!=', $encodedRental->id)
                ->first();

            if (!$duplicate) {
                DB::table('rentals')->where('id', $encodedRental->id)->update(['source' => $normalizedSource]);
                continue;
            }

            // Keep the current row, but with the date it was first detected.
            $firstDetectedAt = min($encodedRental->created_at, $duplicate->created_at);
            DB::table('rentals')->where('id', $duplicate->id)->update(['created_at' => $firstDetectedAt]);
            DB::table('rentals')->where('id', $encodedRental->id)->delete();
        }
    }

    public function down(): void
    {
        // Data normalization, nothing to revert.
    }
};
