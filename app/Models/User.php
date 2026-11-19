<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    public const DELETED_AT = 'dihapus_pada';

    protected $attributes = ['active' => true, 'keanggotaan_aktif' => true];

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['platform_admin'];

    protected function platformAdmin(): Attribute
    {
        return Attribute::get(fn (): bool => $this->kode_role === 'platform_admin');
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'keanggotaan_aktif' => 'boolean'];
    }
}
