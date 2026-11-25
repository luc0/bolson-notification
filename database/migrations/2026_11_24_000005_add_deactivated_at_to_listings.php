<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * deactivated_at: when a listing stopped being published. A listing that comes back after
 * harvesting.relisting_after_days is a new availability, stored as a new listing (ListingMatcher), so the same
 * URL can now be on more than one row and `source` is no longer unique.
 *
 * Existing inactive listings get their updated_at, the closest known date.
 */
return new class extends Migration
{
    private const TABLES = ['rentals', 'lands', 'houses'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('deactivated_at')->nullable()->after('active');
                $table->dropUnique(['source']);
                $table->index('source');
            });

            DB::table($tableName)
                ->where('active', false)
                ->update(['deactivated_at' => DB::raw('coalesce(updated_at, CURRENT_TIMESTAMP)')]);
        }
    }

    /**
     * Fails if a URL was relisted as a new row: merge those first.
     */
    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['source']);
                $table->unique('source');
                $table->dropColumn('deactivated_at');
            });
        }
    }
};
