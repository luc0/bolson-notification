<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly price stats of the listings published each month, per series (Market domain).
 * city / bedrooms null = every city / every bedroom count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_stats', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->string('series', 40);
            $table->string('city', 30)->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->string('currency', 3);
            $table->string('unit', 20);
            $table->unsignedInteger('listings_count');
            $table->decimal('median', 14, 2);
            $table->decimal('average', 14, 2);
            $table->decimal('p25', 14, 2);
            $table->decimal('p75', 14, 2);
            $table->timestamps();

            // city and bedrooms are nullable, so uniqueness is kept by MarketSnapshot (it replaces a whole period).
            $table->index(['series', 'currency', 'city', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_stats');
    }
};
