<?php

// Mensajes del restablecimiento de contraseña de Laravel. La app no ofrece
// ese flujo (el cambio de contraseña va con la actual, en UsuarioController),
// pero se traducen por completitud del fallback_locale.

return [
    'reset'     => 'Tu contraseña ha sido restablecida.',
    'sent'      => 'Te hemos enviado por correo el enlace para restablecer la contraseña.',
    'throttled' => 'Espera un poco antes de volver a intentarlo.',
    'token'     => 'El token de restablecimiento no es válido.',
    'user'      => 'No encontramos ningún usuario con ese correo electrónico.',
];
