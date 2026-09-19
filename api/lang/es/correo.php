<?php

// Textos de los correos que envía la aplicación. El idioma lo fija el
// Mailable con ->locale(), a partir del Accept-Language de la petición.
return [
    'verificacion' => [
        'asunto'  => ':codigo es tu código de verificación de PokeTrade',
        'intro'   => 'Gracias por registrarte. Escribe este código en la web para verificar tu correo:',
        'caduca'  => 'El código caduca en :minutos minutos.',
        'ignorar' => 'Si no has sido tú, ignora este correo: nadie puede entrar sin el código.',
    ],
];
