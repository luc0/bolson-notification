<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `location` is free text (city, neighborhood or address): city (App\Domains\Locations\Enums\City)
 * and neighborhood are resolved from it. Fill existing rows with `php artisan rentals:resolve-cities`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('city', 30)->nullable()->index()->after('location');
            $table->string('neighborhood')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropColumn(['city', 'neighborhood']);
        });
    }
};
