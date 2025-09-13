<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class AnonSubscriber extends Model
{
    use Notifiable, HasPushSubscriptions;

    protected $fillable = ['uuid'];
}
