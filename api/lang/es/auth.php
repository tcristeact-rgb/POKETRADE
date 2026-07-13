<?php

// Mensajes del sistema de autenticación de Laravel. Hoy la app no los usa
// (AuthController tiene los suyos en mensajes.php y jwt-auth no toca este
// fichero), pero van traducidos igualmente: es el fallback_locale, y si algún
// día se usan tienen que salir en español, no dejar un hueco en inglés.

return [
    'failed'   => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña indicada no es correcta.',
    'throttle' => 'Demasiados intentos de acceso. Vuelve a intentarlo en :seconds segundos.',
];
