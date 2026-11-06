<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `price` becomes the raw text returned by the scraper ("$650.000 ARS", "Consultar"),
     * `price_amount` + `price_currency` hold the parsed, filterable value.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('price_amount', 12, 2)->nullable()->after('price');
            $table->string('price_currency', 3)->nullable()->after('price_amount');
        });

        DB::table('rentals')
            ->whereNotNull('price')
            ->update(['price_amount' => DB::raw('price'), 'price_currency' => 'ARS']);

        Schema::table('rentals', function (Blueprint $table) {
            $table->string('price')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('rentals')->update(['price' => DB::raw('price_amount')]);

        Schema::table('rentals', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->change();
            $table->dropColumn(['price_amount', 'price_currency']);
        });
    }
};
