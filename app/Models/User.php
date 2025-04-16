<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'avatar',
        'phone',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    /**
     * Получить роль пользователя.
     *
     * @return BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
    
    /**
     * Получить оператора, связанного с пользователем.
     *
     * @return HasOne
     */
    public function operator(): HasOne
    {
        return $this->hasOne(Operator::class);
    }
    
    /**
     * Проверить, имеет ли пользователь указанную роль.
     *
     * @param string $roleSlug
     * @return bool
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->role && $this->role->slug === $roleSlug;
    }
    
    /**
     * Проверить, имеет ли пользователь указанное разрешение.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return $this->role && $this->role->hasPermission($permission);
    }
    
    /**
     * Проверить, имеет ли пользователь все указанные разрешения.
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return $this->role && $this->role->hasAllPermissions($permissions);
    }
    
    /**
     * Проверить, имеет ли пользователь хотя бы одно из указанных разрешений.
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->role && $this->role->hasAnyPermission($permissions);
    }
    
    /**
     * Проверить, является ли пользователь оператором.
     *
     * @return bool
     */
    public function isOperator(): bool
    {
        return $this->hasRole('operator');
    }
    
    /**
     * Проверить, является ли пользователь клиентом.
     *
     * @return bool
     */
    public function isClient(): bool
    {
        return $this->hasRole('client');
    }
    
    /**
     * Проверить, является ли пользователь администратором.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
}
