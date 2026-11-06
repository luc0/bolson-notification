<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AnonSubscriber moved to App\Domains\Subscriptions and is now registered in the morph map
 * (AppServiceProvider) as "anon_subscriber": update the class names already stored.
 */
return new class extends Migration
{
    private const OLD_CLASS = 'App\Models\AnonSubscriber';

    private const ALIAS = 'anon_subscriber';

    public function up(): void
    {
        DB::table('push_subscriptions')
            ->where('subscribable_type', self::OLD_CLASS)
            ->update(['subscribable_type' => self::ALIAS]);
    }

    public function down(): void
    {
        DB::table('push_subscriptions')
            ->where('subscribable_type', self::ALIAS)
            ->update(['subscribable_type' => self::OLD_CLASS]);
    }
};
