<?php

namespace App\Services;

use App\Mail\CodigoDeVerificacion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Verificación del correo por código de 6 dígitos (spec 2026-09-19-verificacion-de-correo).
//
// El código viaja por correo y se guarda solo su hash, como una contraseña: un
// volcado de la BD no sirve para verificar cuentas ajenas. Caduca a los 15
// minutos y se bloquea al quinto fallo; reenviar genera uno nuevo y anula el
// anterior. El envío es síncrono a propósito: no hay worker de cola en Render.
class VerificacionDeCorreo
{
    public const MINUTOS_DE_VALIDEZ = 15;
    public const INTENTOS_MAXIMOS   = 5;

    // Genera, guarda (hasheado) y envía un código nuevo. Si el envío falla, el
    // código queda guardado igualmente y se deja rastro: el usuario podrá pedir
    // un reenvío, y el registro que lo provocó no debe fallar por Brevo.
    public function enviarCodigo(User $usuario, string $idioma): bool
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $usuario->forceFill([
            'codigo_verificacion' => Hash::make($codigo),
            'codigo_expira_en'    => now()->addMinutes(self::MINUTOS_DE_VALIDEZ),
            'codigo_intentos'     => 0,
        ])->save();

        try {
            Mail::to($usuario->email)->send((new CodigoDeVerificacion($codigo))->locale($idioma));

            return true;
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el código de verificación', [
                'email'     => $usuario->email,
                'excepcion' => get_class($e),
                'mensaje'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verificar(User $usuario, string $codigo): ResultadoVerificacion
    {
        if ($usuario->hasVerifiedEmail()) {
            return ResultadoVerificacion::Ok;
        }

        if (!$usuario->codigo_verificacion || !$usuario->codigo_expira_en || $usuario->codigo_expira_en->isPast()) {
            return ResultadoVerificacion::Caducado;
        }

        if ($usuario->codigo_intentos >= self::INTENTOS_MAXIMOS) {
            return ResultadoVerificacion::Bloqueado;
        }

        if (!Hash::check($codigo, $usuario->codigo_verificacion)) {
            $usuario->increment('codigo_intentos');

            return ResultadoVerificacion::Incorrecto;
        }

        $usuario->forceFill([
            'codigo_verificacion' => null,
            'codigo_expira_en'    => null,
            'codigo_intentos'     => 0,
        ])->save();
        $usuario->markEmailAsVerified();

        return ResultadoVerificacion::Ok;
    }
}
