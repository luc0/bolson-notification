<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly average of the dollar (pesos per dollar), used to convert listing prices (Market domain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->string('dollar', 20); // "blue", "oficial"...
            $table->decimal('ars_per_usd', 12, 2);
            $table->timestamps();

            $table->unique(['period', 'dollar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
