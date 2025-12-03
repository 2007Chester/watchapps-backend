<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchfaceSale extends Model
{
    protected $fillable = [
        'watchface_id',
        'user_id',
        'price',
        'currency',
    ];

    public function watchface()
    {
        return $this->belongsTo(Watchface::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
