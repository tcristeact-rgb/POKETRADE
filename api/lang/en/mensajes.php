<?php

// API messages that end up in front of the user: the frontend renders the
// response's 'error' field verbatim (auth.js, catalogo.js...), so these are
// not developer strings — they are interface.

return [

    // --- Auth ---
    'registrado'      => 'Account created successfully',
    'credenciales'    => 'Incorrect email or password',
    'sesion_cerrada'  => 'You have been logged out',
    'acceso_denegado' => 'Access denied. Administrator permissions are required.',

    // --- Profile ---
    'perfil_actualizado'    => 'Profile updated successfully',
    'password_actualizada'  => 'Password updated successfully',
    'password_incorrecta'   => 'Your current password is not correct',
    'usuario_no_encontrado' => 'User not found',
    'usuario_eliminado'     => 'User deleted successfully',

    // --- Cards and catalogue ---
    'carta_no_encontrada' => 'Card not found',
    'carta_eliminada'     => 'Card deleted successfully',
    'serie_no_encontrada' => 'Series not found',
    'set_no_encontrado'   => 'Set not found',
    'busqueda_sin_filtro' => 'Provide at least one filter: q, tipo or rareza',
    'busqueda_corta'      => 'The search needs at least 2 characters',

    // The external catalogue (TCGdex) is down. Users see this when they open
    // a set that is not cached yet and the third-party API is unavailable.
    'tcgdex_caido' => 'The external catalogue (TCGdex) is not responding right now. Please try again in a few minutes.',

    // --- Inventory ---
    'inventario_carta_no_encontrada' => 'That card is not in your inventory',
    'inventario_carta_eliminada'     => 'Card removed from your inventory',

    // --- Trades ---
    'tradeo_no_encontrado'  => 'Trade not found',
    'tradeo_publicado'      => 'Trade posted successfully',
    'tradeo_actualizado'    => 'Trade updated successfully',
    'tradeo_eliminado'      => 'Trade deleted successfully',
    'tradeo_no_disponible'  => 'This trade is no longer available',
    'tradeo_propio'         => 'You cannot accept your own trade',
    'tradeo_sin_permiso_editar' => 'You do not have permission to modify this trade',
    'tradeo_sin_permiso_borrar' => 'You do not have permission to delete this trade',
    'tradeo_no_publicado'   => 'The trade could not be posted.',
    'tradeo_error_eliminar' => 'The trade could not be deleted',

    // The card name is a parameter and is NOT translated: it is data, and
    // until phase 4 the database only holds it in one language.
    'no_tienes_carta'     => 'You do not have the required card: :carta',
    'no_tienes_ofrecidas' => 'Some of the cards you are trying to offer are not in your inventory.',

    'intercambio_completado' => 'Trade completed successfully',
    'intercambio_fallido'    => 'The trade could not be completed.',

];
