<?php

namespace App\Domains\Subscriptions\Models;

use App\Domains\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * Receives the alerts of a member (user_id is null for subscribers created before members existed).
 */
class Subscriber extends Model
{
    use HasPushSubscriptions, Notifiable;

    protected $fillable = ['user_id'];

    protected static function booted(): void
    {
        // push_subscriptions is polymorphic (no foreign key): clean it up explicitly.
        static::deleting(fn (Subscriber $subscriber) => $subscriber->pushSubscriptions()->delete());
    }

    public static function forUser(User $user): self
    {
        return self::firstOrCreate(['user_id' => $user->id]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
