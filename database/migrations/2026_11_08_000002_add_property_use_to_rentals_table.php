<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * To live in or commercial (App\Domains\Rentals\Enums\PropertyUse).
 * Fill existing rows with `php artisan rentals:classify`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('property_use', 20)->nullable()->index()->after('neighborhood');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropIndex(['property_use']);
            $table->dropColumn('property_use');
        });
    }
};
