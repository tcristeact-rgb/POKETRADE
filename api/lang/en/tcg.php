<?php

// Display names for the TCG types and rarities.
//
// The database stores the KEY ('fire', 'holo-rare'); this file is the only
// thing that decides what they are called on screen. A new language is just
// another file like this one — no card rows to touch, no re-sync.
//
// These are TCGdex's official English names. "Holo Rare" and "Rare Holo" do
// look like a typo, but they are two different rarities: the first belongs to
// the modern sets, the second to the classic ones.

return [

    'tipos' => [
        'grass'     => 'Grass',
        'fire'      => 'Fire',
        'water'     => 'Water',
        'lightning' => 'Lightning',
        'psychic'   => 'Psychic',
        'fighting'  => 'Fighting',
        'darkness'  => 'Darkness',
        'metal'     => 'Metal',
        'fairy'     => 'Fairy',
        'dragon'    => 'Dragon',
        'colorless' => 'Colorless',
    ],

    'rarezas' => [
        'none'                      => 'None',
        'common'                    => 'Common',
        'uncommon'                  => 'Uncommon',
        'rare'                      => 'Rare',
        'holo-rare'                 => 'Holo Rare',
        'holo-rare-v'               => 'Holo Rare V',
        'holo-rare-vmax'            => 'Holo Rare VMAX',
        'holo-rare-vstar'           => 'Holo Rare VSTAR',

        // Classic sets
        'rare-holo'                 => 'Rare Holo',
        'rare-holo-lvx'             => 'Rare Holo LV.X',
        'rare-prime'                => 'Rare PRIME',
        'legend'                    => 'LEGEND',
        'classic-collection'        => 'Classic Collection',

        'double-rare'               => 'Double Rare',
        'ultra-rare'                => 'Ultra Rare',
        'secret-rare'               => 'Secret Rare',
        'hyper-rare'                => 'Hyper Rare',
        'mega-hyper-rare'           => 'Mega Hyper Rare',
        'illustration-rare'         => 'Illustration Rare',
        'special-illustration-rare' => 'Special Illustration Rare',
        'radiant-rare'              => 'Radiant Rare',
        'amazing-rare'              => 'Amazing Rare',
        'ace-spec-rare'             => 'ACE SPEC Rare',
        'black-white-rare'          => 'Black White Rare',
        'full-art-trainer'          => 'Full Art Trainer',

        'shiny-rare'                => 'Shiny Rare',
        'shiny-rare-v'              => 'Shiny Rare V',
        'shiny-rare-vmax'           => 'Shiny Rare VMAX',
        'shiny-ultra-rare'          => 'Shiny Ultra Rare',

        'promo'                     => 'Promo',

        // Pokémon Pocket
        'one-diamond'               => 'One Diamond',
        'two-diamond'               => 'Two Diamond',
        'three-diamond'             => 'Three Diamond',
        'four-diamond'              => 'Four Diamond',
        'one-star'                  => 'One Star',
        'two-star'                  => 'Two Star',
        'three-star'                => 'Three Star',
        'one-shiny'                 => 'One Shiny',
        'two-shiny'                 => 'Two Shiny',
        'crown'                     => 'Crown',
    ],

];
