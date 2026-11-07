<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short descriptive title written by the AI from the detail page (`content` keeps the agency's title).
 * Fill existing rows with `php artisan rentals:fetch-details --missing-titles`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('title')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
