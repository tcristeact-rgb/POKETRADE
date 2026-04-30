<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

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
    ];

    // Campos ocultos en respuestas JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Métodos requeridos por JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}