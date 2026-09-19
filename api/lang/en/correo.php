<?php

// Texts of the emails the application sends. The language is set by the
// Mailable with ->locale(), from the request's Accept-Language.
return [
    'verificacion' => [
        'asunto'  => ':codigo is your PokeTrade verification code',
        'intro'   => 'Thanks for signing up. Enter this code on the website to verify your email:',
        'caduca'  => 'The code expires in :minutos minutes.',
        'ignorar' => 'If this was not you, ignore this email: nobody can sign in without the code.',
    ],
];
