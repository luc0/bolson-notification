<?php

namespace App\Domains\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class AnonSubscriber extends Model
{
    use HasPushSubscriptions, Notifiable;

    protected $fillable = ['uuid'];
}
