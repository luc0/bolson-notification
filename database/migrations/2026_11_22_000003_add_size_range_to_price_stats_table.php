<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stats per size range (SizeRange key, e.g. "0-1000"); null = every size. Filled by market:snapshot --backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_stats', function (Blueprint $table) {
            $table->string('size_range', 30)->nullable()->after('bedrooms');
        });
    }

    public function down(): void
    {
        Schema::table('price_stats', function (Blueprint $table) {
            $table->dropColumn('size_range');
        });
    }
};
