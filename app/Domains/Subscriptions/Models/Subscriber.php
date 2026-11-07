<?php

namespace App\Domains\Subscriptions\Models;

use App\Domains\Members\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * Receives the alerts of a member (user_id is null for subscribers created before members existed),
 * by web push (one push subscription per browser) and/or Telegram (linked chat + telegram_enabled).
 */
class Subscriber extends Model
{
    use HasPushSubscriptions, Notifiable;

    protected $fillable = ['user_id', 'telegram_chat_id', 'telegram_link_token', 'telegram_enabled'];

    protected $casts = [
        'telegram_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        // push_subscriptions is polymorphic (no foreign key): clean it up explicitly.
        static::deleting(fn (Subscriber $subscriber) => $subscriber->pushSubscriptions()->delete());
    }

    public static function forUser(User $user): self
    {
        return self::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Subscribers that get at least one channel.
     */
    public function scopeNotifiable(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereHas('pushSubscriptions')
            ->orWhere(fn (Builder $query) => $query->whereNotNull('telegram_chat_id')->where('telegram_enabled', true)));
    }

    public function hasTelegramLinked(): bool
    {
        return $this->telegram_chat_id !== null;
    }

    public function receivesTelegram(): bool
    {
        return $this->hasTelegramLinked() && $this->telegram_enabled;
    }

    /**
     * Code for the "Conectar Telegram" link, created the first time it's needed.
     */
    public function telegramLinkToken(): string
    {
        if ($this->telegram_link_token === null) {
            $this->update(['telegram_link_token' => Str::random(32)]);
        }

        return $this->telegram_link_token;
    }

    /**
     * The chat moves to this subscriber if another one had it (e.g. the member re-created their account).
     */
    public function linkTelegramChat(string $chatId): void
    {
        self::where('telegram_chat_id', $chatId)->whereKeyNot($this->getKey())->update(['telegram_chat_id' => null]);

        $this->update(['telegram_chat_id' => $chatId, 'telegram_enabled' => true]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
