<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Watchface extends Model
{
    protected $fillable = [
        'developer_id',
        'title',
        'slug',
        'description',
        'price',
        'discount_price',
        'discount_end_at',
        'is_free',
        'version',
        'type',
        'status',
    ];

    protected $casts = [
        'is_free'         => 'boolean',
        'discount_end_at' => 'datetime',
    ];

    public function files()
    {
        return $this->hasMany(WatchfaceFile::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'watchface_category');
    }
}
