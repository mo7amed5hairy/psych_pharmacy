<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'employee_code',
        'password_hash',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function hasPermission(string $module): bool
    {
        // Hardcoded admins Esraa (7777) and Reem (1010) have all permissions by default
        if (in_array($this->employee_code, ['7777', '1010'])) {
            return true;
        }

        return $this->permissions()
            ->where('module', $module)
            ->where('is_granted', true)
            ->exists();
    }

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
        ];
    }

    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
