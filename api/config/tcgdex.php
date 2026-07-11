<?php

// ─── Configuración del catálogo TCGdex ───
// https://tcgdex.dev · consumida por TcgdexService, el comando
// tcgdex:sync-sets y la búsqueda global del catálogo.

return [

    // Series EXCLUIDAS del catálogo, por su ID de TCGdex. Añadir o
    // quitar aquí no requiere tocar código: el sync las salta, la
    // búsqueda global filtra sus cartas y tcgdex:purgar-excluidos
    // limpia lo que ya estuviera importado en la BD.
    //
    //   tcgp → Pokémon TCG Pocket: juego móvil, no son cartas físicas
    //          y no encajan en una web de trading real
    //   mc   → Colección de McDonald's: TCGdex no tiene sus assets
    //          (sin logos de serie/sets y sin imágenes de carta en
    //          ningún idioma)
    //   tk   → Kits de Entrenador: producto didáctico sin valor de
    //          colección y sin logos en TCGdex
    'series_excluidas' => ['tcgp', 'mc', 'tk'],

];
