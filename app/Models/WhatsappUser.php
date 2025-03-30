<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappUser extends Model
{
    protected $fillable = ['name', 'phone', 'active'];
}
