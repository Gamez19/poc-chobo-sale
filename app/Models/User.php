<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /**
     * Available application themes.
     *
     * @var array<int, string>
     */
    public const THEMES = ['claro', 'cacao', 'oscuro'];

    /**
     * Human-readable theme names.
     *
     * @var array<string, string>
     */
    public const THEME_LABELS = [
        'claro' => 'Claro',
        'cacao' => 'Cacao',
        'oscuro' => 'Noche',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
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
