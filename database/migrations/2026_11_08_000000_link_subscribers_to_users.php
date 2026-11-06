<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Push subscribers belong to a member now: anon_subscribers -> subscribers with user_id.
 * Existing anonymous rows keep working (user_id null) until the member subscribes again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('anon_subscribers', 'subscribers');

        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropUnique('anon_subscribers_uuid_unique'); // index keeps the old table name after the rename
            $table->dropColumn('uuid');
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('push_subscriptions')
            ->where('subscribable_type', 'anon_subscriber')
            ->update(['subscribable_type' => 'subscriber']);
    }

    public function down(): void
    {
        DB::table('push_subscriptions')
            ->where('subscribable_type', 'subscriber')
            ->update(['subscribable_type' => 'anon_subscriber']);

        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->uuid('uuid')->nullable()->unique('anon_subscribers_uuid_unique');
        });

        Schema::rename('subscribers', 'anon_subscribers');
    }
};
