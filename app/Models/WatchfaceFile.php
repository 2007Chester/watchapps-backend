<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchfaceFile extends Model
{
    protected $fillable = [
        'watchface_id', 'upload_id', 'type', 'sort_order'
    ];
}
