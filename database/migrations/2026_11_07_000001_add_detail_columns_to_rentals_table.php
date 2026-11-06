<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data that only exists on each listing's detail page (not on the scraped listing page).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('covered_area', 10, 2)->nullable()->after('bathrooms');
            $table->decimal('total_area', 12, 2)->nullable()->after('covered_area');
            $table->unsignedSmallInteger('bedrooms')->nullable()->after('rooms');
            $table->unsignedSmallInteger('parking_spaces')->nullable()->after('total_area');
            $table->unsignedSmallInteger('age_years')->nullable()->after('parking_spaces');
            $table->json('services')->nullable()->after('age_years');
            $table->timestamp('details_fetched_at')->nullable()->after('services');
            $table->unsignedTinyInteger('details_attempts')->default(0)->after('details_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn([
                'covered_area', 'total_area', 'bedrooms', 'parking_spaces', 'age_years',
                'services', 'details_fetched_at', 'details_attempts',
            ]);
        });
    }
};
