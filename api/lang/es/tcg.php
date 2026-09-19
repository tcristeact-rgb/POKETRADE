<?php

// Nombres que ve el usuario para los tipos y las categorías de rareza.
//
// La BD guarda la CLAVE ('fire'; y en rareza la clave fina de TCGdex,
// 'holo-rare-v', que App\Support\Rarezas agrupa en diez categorías). Esto es
// lo único que decide cómo se llaman en pantalla. Un idioma nuevo = otro
// fichero como este, sin tocar ni una carta.
//
// Ojo: esto NO es una copia de lo que devuelve TCGdex (eso está en
// App\Support\CatalogoTcg, que es protocolo). Aquí decidimos qué se ENSEÑA.

return [

    'tipos' => [
        'grass'     => 'Planta',
        'fire'      => 'Fuego',
        'water'     => 'Agua',
        'lightning' => 'Rayo',
        'psychic'   => 'Psíquico',
        'fighting'  => 'Lucha',
        'darkness'  => 'Oscura',
        'metal'     => 'Metálica',
        'fairy'     => 'Hada',
        'dragon'    => 'Dragón',
        'colorless' => 'Incolora',
    ],

    // Las diez categorías de la taxonomía cerrada (App\Support\Rarezas), en
    // su orden canónico. "Excepciones" es el cajón de promos, sets
    // conmemorativos, Pocket y cualquier rareza que no lleve uno de los
    // nueve símbolos.
    'categorias' => [
        'comun'                => 'Común',
        'infrecuente'          => 'Infrecuente',
        'rara'                 => 'Rara',
        'doble_rara'           => 'Doble Rara',
        'ace_spec'             => 'AS Táctico',
        'ilustracion'          => 'Rara Ilustración',
        'ultra_rara'           => 'Ultra Rara',
        'ilustracion_especial' => 'Rara Ilustración Especial',
        'hiper_rara'           => 'Hiper Rara',
        'excepciones'          => 'Excepciones',
    ],

];
