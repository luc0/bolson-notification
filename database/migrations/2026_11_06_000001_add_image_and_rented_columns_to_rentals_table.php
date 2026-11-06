<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->text('image_url')->nullable()->after('site_path');
            // Last image URL that was successfully checked for a "rented" stamp, to never check the same image twice.
            $table->text('image_checked_url')->nullable()->after('image_url');
            $table->timestamp('rented_at')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'image_checked_url', 'rented_at']);
        });
    }
};
