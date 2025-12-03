<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Какие поля можно массово заполнять.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'brand_name',
        'logo_upload_id',
        'onboarding_completed',
    ];

    /**
     * Скрытые поля.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Приведение типов.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'onboarding_completed' => 'boolean',
    ];

    /**
     * Связь: пользователь → список ролей.
     */
    public function roles()
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Возвращает массив всех ролей пользователя.
     *
     * Пример:
     * ["user", "developer"]
     */
    public function getRoleNames()
    {
        return $this->roles()->pluck('role')->toArray();
    }

    /**
     * Основная роль пользователя.
     *
     * Логика приоритетов:
     * admin → developer → user
     */
    public function getPrimaryRoleAttribute()
    {
        $roles = $this->getRoleNames();

        if (in_array('admin', $roles)) {
            return 'admin';
        }

        if (in_array('developer', $roles)) {
            return 'developer';
        }

        return 'user';
    }

    /**
     * Связь: пользователь → логотип (upload)
     */
    public function logo()
    {
        return $this->belongsTo(Upload::class, 'logo_upload_id');
    }

    /**
     * Проверка наличия хотя бы одной из указанных ролей
     */
    public function hasAnyRole(array $roles): bool
    {
        $userRoles = $this->getRoleNames();
        return !empty(array_intersect($roles, $userRoles));
    }
}
