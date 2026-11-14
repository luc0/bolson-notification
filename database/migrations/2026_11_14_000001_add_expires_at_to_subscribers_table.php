<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions expire: only subscribers with expires_at in the future get alerts and see the latest rentals.
 * Every current member (with or without a subscriber row) and every legacy subscriber starts with one
 * subscription period, so nobody loses the alerts the day this is deployed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('expiry_reminder_sent_at')->nullable();
        });

        $now = now();
        $expiresAt = $now->copy()->addDays((int) config('subscriptions.duration_days'));

        $usersWithoutSubscriber = DB::table('users')
            ->whereNotIn('id', DB::table('subscribers')->whereNotNull('user_id')->select('user_id'))
            ->pluck('id');
        foreach ($usersWithoutSubscriber as $userId) {
            DB::table('subscribers')->insert(['user_id' => $userId, 'telegram_enabled' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('subscribers')->update(['expires_at' => $expiresAt]);
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['expires_at', 'expiry_reminder_sent_at']);
        });
    }
};
