<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * City (App\Domains\Locations\Enums\City) and neighborhood resolved from the land's texts.
 * Fill existing rows with `php artisan lands:classify` (no AI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lands', function (Blueprint $table) {
            $table->string('city', 30)->nullable()->index()->after('location');
            $table->string('neighborhood')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('lands', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropColumn(['city', 'neighborhood']);
        });
    }
};
