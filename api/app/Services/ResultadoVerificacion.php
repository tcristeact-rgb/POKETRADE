<?php

namespace App\Services;

// Qué pasó al comprobar un código. Cada caso tiene su mensaje en
// lang/*/mensajes.php; el controlador solo traduce el caso a HTTP.
enum ResultadoVerificacion: string
{
    case Ok         = 'ok';
    case Incorrecto = 'incorrecto';
    case Caducado   = 'caducado';
    case Bloqueado  = 'bloqueado';

    public function claveMensaje(): string
    {
        return match ($this) {
            self::Ok         => 'mensajes.correo_verificado',
            self::Incorrecto => 'mensajes.codigo_incorrecto',
            self::Caducado   => 'mensajes.codigo_caducado',
            self::Bloqueado  => 'mensajes.codigo_bloqueado',
        };
    }
}
