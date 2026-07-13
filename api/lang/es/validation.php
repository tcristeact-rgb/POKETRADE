<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación
    |--------------------------------------------------------------------------
    |
    | Traducción completa del validation.php de Laravel, no solo de las reglas
    | que la app usa hoy: si mañana alguien añade una regla nueva (regex, size,
    | mimes...), el mensaje sale ya en español. Si faltara, Laravel caería al
    | fallback_locale y le colaría un mensaje en inglés al usuario español, sin
    | avisar de nada.
    |
    | :attribute se sustituye por el nombre del campo (ver 'attributes' al final).
    |
    */

    'accepted' => 'El campo :attribute debe ser aceptado.',
    'accepted_if' => 'El campo :attribute debe ser aceptado cuando :other sea :value.',
    'active_url' => 'El campo :attribute debe ser una URL válida.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => 'El campo :attribute solo debe contener letras.',
    'alpha_dash' => 'El campo :attribute solo debe contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'El campo :attribute solo debe contener letras y números.',
    'any_of' => 'El campo :attribute no es válido.',
    'array' => 'El campo :attribute debe ser una lista.',
    'ascii' => 'El campo :attribute solo debe contener caracteres alfanuméricos y símbolos de un solo byte.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El campo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'can' => 'El campo :attribute contiene un valor no autorizado.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'contains' => 'Al campo :attribute le falta un valor obligatorio.',
    'current_password' => 'La contraseña no es correcta.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'date_equals' => 'El campo :attribute debe ser una fecha igual a :date.',
    'date_format' => 'El campo :attribute debe corresponder con el formato :format.',
    'decimal' => 'El campo :attribute debe tener :decimal decimales.',
    'declined' => 'El campo :attribute debe ser rechazado.',
    'declined_if' => 'El campo :attribute debe ser rechazado cuando :other sea :value.',
    'different' => 'Los campos :attribute y :other deben ser diferentes.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'Las dimensiones de la imagen :attribute no son válidas.',
    'distinct' => 'El campo :attribute tiene un valor duplicado.',
    'doesnt_contain' => 'El campo :attribute no debe contener ninguno de los siguientes valores: :values.',
    'doesnt_end_with' => 'El campo :attribute no debe terminar por ninguno de los siguientes valores: :values.',
    'doesnt_start_with' => 'El campo :attribute no debe empezar por ninguno de los siguientes valores: :values.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'encoding' => 'El campo :attribute debe estar codificado en :encoding.',
    'ends_with' => 'El campo :attribute debe terminar por uno de los siguientes valores: :values.',
    'enum' => 'El valor seleccionado en :attribute no es válido.',
    'exists' => 'El valor seleccionado en :attribute no es válido.',
    'extensions' => 'El campo :attribute debe tener una de las siguientes extensiones: :values.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute no puede estar vacío.',
    'gt' => [
        'array' => 'El campo :attribute debe tener más de :value elementos.',
        'file' => 'El campo :attribute debe pesar más de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe tener :value elementos o más.',
        'file' => 'El campo :attribute debe pesar :value kilobytes o más.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o más.',
    ],
    'hex_color' => 'El campo :attribute debe ser un color hexadecimal válido.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor seleccionado en :attribute no es válido.',
    'in_array' => 'El campo :attribute debe existir en :other.',
    'in_array_keys' => 'El campo :attribute debe contener al menos una de las siguientes claves: :values.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'ip' => 'El campo :attribute debe ser una dirección IP válida.',
    'ipv4' => 'El campo :attribute debe ser una dirección IPv4 válida.',
    'ipv6' => 'El campo :attribute debe ser una dirección IPv6 válida.',
    'json' => 'El campo :attribute debe ser una cadena JSON válida.',
    'list' => 'El campo :attribute debe ser una lista.',
    'lowercase' => 'El campo :attribute debe estar en minúsculas.',
    'lt' => [
        'array' => 'El campo :attribute debe tener menos de :value elementos.',
        'file' => 'El campo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute no debe tener más de :value elementos.',
        'file' => 'El campo :attribute debe pesar :value kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o menos.',
    ],
    'mac_address' => 'El campo :attribute debe ser una dirección MAC válida.',
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'file' => 'El campo :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
    ],
    'max_digits' => 'El campo :attribute no debe tener más de :max dígitos.',
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'min_digits' => 'El campo :attribute debe tener al menos :min dígitos.',
    'missing' => 'El campo :attribute no debe estar presente.',
    'missing_if' => 'El campo :attribute no debe estar presente cuando :other sea :value.',
    'missing_unless' => 'El campo :attribute no debe estar presente a menos que :other sea :value.',
    'missing_with' => 'El campo :attribute no debe estar presente si :values está presente.',
    'missing_with_all' => 'El campo :attribute no debe estar presente si :values están presentes.',
    'multiple_of' => 'El campo :attribute debe ser múltiplo de :value.',
    'not_in' => 'El valor seleccionado en :attribute no es válido.',
    'not_regex' => 'El formato del campo :attribute no es válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'password' => [
        'letters' => 'El campo :attribute debe contener al menos una letra.',
        'mixed' => 'El campo :attribute debe contener al menos una mayúscula y una minúscula.',
        'numbers' => 'El campo :attribute debe contener al menos un número.',
        'symbols' => 'El campo :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'El valor de :attribute ha aparecido en una filtración de datos. Elige otro distinto.',
    ],
    'present' => 'El campo :attribute debe estar presente.',
    'present_if' => 'El campo :attribute debe estar presente cuando :other sea :value.',
    'present_unless' => 'El campo :attribute debe estar presente a menos que :other sea :value.',
    'present_with' => 'El campo :attribute debe estar presente si :values está presente.',
    'present_with_all' => 'El campo :attribute debe estar presente si :values están presentes.',
    'prohibited' => 'El campo :attribute está prohibido.',
    'prohibited_if' => 'El campo :attribute está prohibido cuando :other sea :value.',
    'prohibited_if_accepted' => 'El campo :attribute está prohibido cuando se acepta :other.',
    'prohibited_if_declined' => 'El campo :attribute está prohibido cuando se rechaza :other.',
    'prohibited_unless' => 'El campo :attribute está prohibido a menos que :other esté entre :values.',
    'prohibits' => 'El campo :attribute impide que :other esté presente.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_array_keys' => 'El campo :attribute debe contener entradas para: :values.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_if_accepted' => 'El campo :attribute es obligatorio cuando se acepta :other.',
    'required_if_declined' => 'El campo :attribute es obligatorio cuando se rechaza :other.',
    'required_unless' => 'El campo :attribute es obligatorio a menos que :other esté entre :values.',
    'required_with' => 'El campo :attribute es obligatorio si :values está presente.',
    'required_with_all' => 'El campo :attribute es obligatorio si :values están presentes.',
    'required_without' => 'El campo :attribute es obligatorio si :values no está presente.',
    'required_without_all' => 'El campo :attribute es obligatorio si no está presente ninguno de :values.',
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe contener :size elementos.',
        'file' => 'El campo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El campo :attribute debe empezar por uno de los siguientes valores: :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria válida.',
    'unique' => 'El valor de :attribute ya está en uso.',
    'uploaded' => 'No se pudo subir el archivo :attribute.',
    'uppercase' => 'El campo :attribute debe estar en mayúsculas.',
    'url' => 'El campo :attribute debe ser una URL válida.',
    'ulid' => 'El campo :attribute debe ser un ULID válido.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación personalizados
    |--------------------------------------------------------------------------
    |
    | Mensajes para una pareja campo+regla concreta, cuando el genérico no
    | dice lo suficiente. El de la contraseña lo es: "El campo contraseña debe
    | tener al menos 6 caracteres" es correcto, pero el formulario ya promete
    | exactamente esta frase, y las dos deben decir lo mismo.
    |
    */

    'custom' => [
        // Tipo y rareza son un conjunto cerrado (ver App\Support\CatalogoTcg):
        // un valor que no esté en él se rechaza en vez de acabar en la BD sin
        // traducción posible.
        'tcg' => [
            'desconocido' => 'El valor de :attribute no existe en el catálogo del TCG.',
        ],
        'password' => [
            'min' => 'La contraseña debe tener al menos :min caracteres.',
        ],
        'password_nuevo' => [
            'min' => 'La nueva contraseña debe tener al menos :min caracteres.',
        ],
        'email' => [
            'unique' => 'Ya existe una cuenta con ese correo electrónico.',
        ],
        'cartas_ofrece' => [
            'required' => 'Selecciona al menos una carta que ofrecer.',
            'min'      => 'Selecciona al menos una carta que ofrecer.',
        ],
        'cartas_busca' => [
            'required' => 'Selecciona al menos una carta que buscar.',
            'min'      => 'Selecciona al menos una carta que buscar.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres de los campos
    |--------------------------------------------------------------------------
    |
    | Sustituyen a :attribute en los mensajes. Sin esto, el usuario leería
    | "El campo password_actual es obligatorio", con el nombre interno de la
    | columna asomando por la interfaz.
    |
    */

    'attributes' => [
        'nombre'           => 'nombre',
        'apellido'         => 'apellido',
        'email'            => 'correo electrónico',
        'password'         => 'contraseña',
        'password_actual'  => 'contraseña actual',
        'password_nuevo'   => 'nueva contraseña',
        'fecha_nacimiento' => 'fecha de nacimiento',
        'nacionalidad'     => 'nacionalidad',
        'avatar_url'       => 'URL del avatar',
        'descripcion'      => 'descripción',
        'estado'           => 'estado',
        'cartas_ofrece'    => 'cartas que ofreces',
        'cartas_busca'     => 'cartas que buscas',
        'carta_id'         => 'carta',
        'cantidad'         => 'cantidad',
        'tipo'             => 'tipo',
        'rareza'           => 'rareza',
        'imagen_url'       => 'URL de la imagen',
    ],

];
