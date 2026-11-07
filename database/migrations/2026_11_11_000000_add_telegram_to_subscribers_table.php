<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram channel: the member links their chat from the profile (t.me/<bot>?start=<telegram_link_token>)
 * and can turn the alerts off (telegram_enabled) without unlinking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->unique();
            $table->string('telegram_link_token', 64)->nullable()->unique();
            $table->boolean('telegram_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropUnique(['telegram_link_token']);
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token', 'telegram_enabled']);
        });
    }
};
