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
        // Esraa (7777) and Reem (1010) start with every permission enabled, but
        // an explicit "off" row saved from the permissions screen must win so
        // an admin can switch any module off for them like any other user.
        if (in_array($this->employee_code, ['7777', '1010'], true)) {
            $permission = $this->permissions()
                ->where('module', $module)
                ->first(['is_granted']);

            return $permission ? (bool) $permission->is_granted : true;
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
