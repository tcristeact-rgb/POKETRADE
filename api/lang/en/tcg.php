<?php

// Display names for the TCG types and rarity categories.
//
// The database stores the KEY ('fire'; for rarity, TCGdex's fine-grained key,
// 'holo-rare-v', which App\Support\Rarezas groups into ten categories). This
// file is the only thing that decides what they are called on screen. A new
// language is just another file like this one — no card rows to touch.

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

    // The ten categories of the closed taxonomy (App\Support\Rarezas), in
    // canonical order. "Other" is the drawer for promos, commemorative sets,
    // Pocket and any rarity that does not carry one of the nine symbols.
    'categorias' => [
        'comun'                => 'Common',
        'infrecuente'          => 'Uncommon',
        'rara'                 => 'Rare',
        'doble_rara'           => 'Double Rare',
        'ace_spec'             => 'Ace Spec',
        'ilustracion'          => 'Illustration Rare',
        'ultra_rara'           => 'Ultra Rare',
        'ilustracion_especial' => 'Special Illustration Rare',
        'hiper_rara'           => 'Hyper Rare',
        'excepciones'          => 'Other',
    ],

];
