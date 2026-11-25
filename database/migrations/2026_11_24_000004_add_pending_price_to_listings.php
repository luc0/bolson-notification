<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A new price read once waits here until a second read confirms it (PriceChangeDetector), so an AI misreading
 * doesn't become a price change.
 */
return new class extends Migration
{
    private const TABLES = ['rentals' => 12, 'lands' => 14, 'houses' => 14];

    public function up(): void
    {
        foreach (self::TABLES as $tableName => $digits) {
            Schema::table($tableName, function (Blueprint $table) use ($digits) {
                $table->string('pending_price')->nullable()->after('price_currency');
                $table->decimal('pending_price_amount', $digits, 2)->nullable()->after('pending_price');
                $table->string('pending_price_currency', 3)->nullable()->after('pending_price_amount');
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['pending_price', 'pending_price_amount', 'pending_price_currency']);
            });
        }
    }
};
