<?php

use App\Domains\Sources\Data\Source;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * external_id: the agency's own id of a listing (Source::listingIdFrom), to recognize a listing whose URL changed.
 * Existing listings get it from their current URL; the importers fill it from then on.
 */
return new class extends Migration
{
    private const TABLES = ['rentals', 'lands', 'houses'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('external_id', 64)->nullable()->after('source');
                $table->index(['site_path', 'external_id']);
            });
        }

        $this->backfill('rentals', Source::rentals());
        $this->backfill('lands', Source::lands());
        $this->backfill('houses', Source::houses());
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['site_path', 'external_id']);
                $table->dropColumn('external_id');
            });
        }
    }

    /**
     * @param  Source[]  $sources
     */
    private function backfill(string $tableName, array $sources): void
    {
        foreach ($sources as $source) {
            DB::table($tableName)
                ->where('site_path', $source->listingUrl)
                ->select(['id', 'source'])
                ->lazyById()
                ->each(function (object $listing) use ($tableName, $source) {
                    $externalId = $source->listingIdFrom($listing->source);
                    if ($externalId === null) {
                        return;
                    }

                    DB::table($tableName)->where('id', $listing->id)->update(['external_id' => $externalId]);
                });
        }
    }
};
