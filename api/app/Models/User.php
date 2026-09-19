<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as VerificaCorreo;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

// MustVerifyEmail solo por hasVerifiedEmail() y markEmailAsVerified(): la
// verificación va por código de 6 dígitos (VerificacionDeCorreo), no por el
// enlace firmado de Laravel, que además encolaría y aquí no hay worker.
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use Notifiable, VerificaCorreo;

    // Campos que se pueden rellenar masivamente
    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'password',
        'rol',
        'fecha_nacimiento',
        'nacionalidad',
        'avatar_url',
        'email_verified_at',
    ];

    // Campos ocultos en respuestas JSON
    protected $hidden = [
        'password',
        'remember_token',
        'codigo_verificacion',
        'codigo_expira_en',
        'codigo_intentos',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'codigo_expira_en'  => 'datetime',
        ];
    }

    // Métodos requeridos por JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function inventario()
    {
        return $this->hasMany(Inventario::class, 'user_id');
    }

    public function tradeos()
    {
        return $this->hasMany(Tradeo::class, 'user_id');
    }
}