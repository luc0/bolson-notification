<?php

namespace App\Domains\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSubscriber extends Model
{
    protected $table = 'whatsapp_users';

    protected $fillable = ['name', 'phone', 'active'];
}
