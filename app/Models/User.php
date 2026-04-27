<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Reserved for future admin authentication. The public storefront does NOT
 * use auth — there is no login UI, no Sanctum guard, no /api/admin user-bound
 * routes (backoffice writes are gated by the `backoffice` middleware token,
 * not by a User session). Keep the model + factory + migration so adding
 * admin auth later is straightforward; do NOT wire `auth:sanctum` to it
 * without first defining who/what registers users.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
