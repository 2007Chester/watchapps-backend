<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
    ];

    // ВАЖНО: timestamps ВКЛЮЧЕНЫ, потому что они есть в миграции
    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
