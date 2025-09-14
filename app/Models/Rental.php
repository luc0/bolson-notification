<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    protected $fillable = [
        'source',
        'content',
        'description',
        'price',
        'location',
        'rooms',
        'bathrooms',
        'source_path',
    ];
}
