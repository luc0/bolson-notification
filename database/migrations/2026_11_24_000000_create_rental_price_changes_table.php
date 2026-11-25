<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price history of each rental (Rentals domain). The first row is the price it was found with,
 * existing rentals get it from their current price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('price')->nullable();
            $table->decimal('price_amount', 12, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->string('match_method', 20);
            $table->decimal('match_confidence', 3, 2);
            $table->timestamp('observed_at');
        });

        DB::table('rental_price_changes')->insertUsing(
            ['rental_id', 'price', 'price_amount', 'price_currency', 'match_method', 'match_confidence', 'observed_at'],
            DB::table('rentals')
                ->select(['id', 'price', 'price_amount', 'price_currency'])
                ->selectRaw("'created', 1, coalesce(created_at, CURRENT_TIMESTAMP)")
                ->orderBy('id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_price_changes');
    }
};
