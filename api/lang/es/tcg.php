<?php

// Nombres que ve el usuario para los tipos y rarezas del TCG.
//
// La BD guarda la CLAVE ('fire', 'holo-rare'); esto es lo único que decide
// cómo se llaman en pantalla. Un idioma nuevo = otro fichero como este, sin
// tocar ni una carta.
//
// Ojo: esto NO es una copia de lo que devuelve TCGdex (eso está en
// App\Support\CatalogoTcg, que es protocolo). Aquí podemos hacerlo MEJOR, y
// en varios sitios lo hacemos: el catálogo español de TCGdex deja sin
// traducir "Uncommon" (92 de nuestras cartas), "Shiny rare", "One Shiny"...
// y aquí sí tienen nombre en español.

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

    'rarezas' => [
        'none'                      => 'Ninguna',
        'common'                    => 'Común',
        'uncommon'                  => 'Poco Común',          // TCGdex la deja en inglés
        'rare'                      => 'Rara',
        'holo-rare'                 => 'Holo Rara',
        'holo-rare-v'               => 'Holo Rara V',
        'holo-rare-vmax'            => 'Holo Rara VMAX',
        'holo-rare-vstar'           => 'Holo Rara VSTAR',

        // Rarezas de los sets clásicos, que TCGdex solo tiene en inglés. El
        // "(clásica)" es cosecha propia: sin él aparecerían dos "Holo Rara"
        // idénticas en el desplegable y no habría forma de elegir.
        'rare-holo'                 => 'Holo Rara (clásica)',
        'rare-holo-lvx'             => 'Holo Rara LV.X',
        'rare-prime'                => 'Rara PRIME',
        'legend'                    => 'LEGEND',
        'classic-collection'        => 'Colección Clásica',

        'double-rare'               => 'Rara Doble',
        'ultra-rare'                => 'Ultra Rara',
        'secret-rare'               => 'Rara Secreta',
        'hyper-rare'                => 'Rara Híper',
        'mega-hyper-rare'           => 'Mega Hiper Rara',
        'illustration-rare'         => 'Rara Ilustración',
        'special-illustration-rare' => 'Rara Ilustración Especial',
        'radiant-rare'              => 'Rara Radiante',
        'amazing-rare'              => 'Increíbles',
        'ace-spec-rare'             => 'Rara AS TÁCTICO',
        'black-white-rare'          => 'Rara Blanca y Negra',
        'full-art-trainer'          => 'Entrenador de arte completo',

        // Variocolor: TCGdex las deja en inglés en su catálogo español
        'shiny-rare'                => 'Rara Variocolor',
        'shiny-rare-v'              => 'Rara Variocolor V',
        'shiny-rare-vmax'           => 'Rara Variocolor VMAX',
        'shiny-ultra-rare'          => 'Rara Ultra Variocolor',

        'promo'                     => 'Promo',

        // Pokémon Pocket
        'one-diamond'               => 'Un Diamante',
        'two-diamond'               => 'Dos Diamantes',
        'three-diamond'             => 'Tres Diamantes',
        'four-diamond'              => 'Cuatro Diamantes',
        'one-star'                  => 'Una Estrella',
        'two-star'                  => 'Dos Estrellas',
        'three-star'                => 'Tres Estrellas',
        'one-shiny'                 => 'Un Variocolor',
        'two-shiny'                 => 'Dos Variocolores',
        'crown'                     => 'Corona',
    ],

];
