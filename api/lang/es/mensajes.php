<?php

// Mensajes de la API que acaban a la vista del usuario: el frontend pinta
// el campo 'error' de la respuesta tal cual (auth.js, catalogo.js...), así
// que esto NO es texto para desarrolladores, es interfaz.

return [

    // --- Auth ---
    'registrado'         => 'Usuario registrado correctamente',
    'credenciales'       => 'Email o contraseña incorrectos',
    'sesion_cerrada'     => 'Sesión cerrada correctamente',
    'acceso_denegado'    => 'Acceso denegado. Se requieren permisos de administrador.',

    // --- Perfil ---
    'perfil_actualizado'   => 'Perfil actualizado correctamente',
    'password_actualizada' => 'Contraseña actualizada correctamente',
    'password_incorrecta'  => 'La contraseña actual no es correcta',
    'usuario_no_encontrado' => 'Usuario no encontrado',
    'usuario_eliminado'     => 'Usuario eliminado correctamente',

    // --- Cartas y catálogo ---
    'carta_no_encontrada' => 'Carta no encontrada',
    'carta_eliminada'     => 'Carta eliminada correctamente',
    'serie_no_encontrada' => 'Serie no encontrada',
    'set_no_encontrado'   => 'Set no encontrado',
    'busqueda_sin_filtro' => 'Indica al menos un filtro: q, tipo o rareza',
    'busqueda_corta'      => 'La búsqueda necesita al menos 2 caracteres',

    // El catálogo externo (TCGdex) no responde. Lo ve el usuario cuando abre
    // un set que aún no está cacheado y la API de terceros está caída.
    'tcgdex_caido' => 'El catálogo externo (TCGdex) no responde ahora mismo. Inténtalo de nuevo en unos minutos.',

    // --- Inventario ---
    'inventario_carta_no_encontrada' => 'Carta no encontrada en tu inventario',
    'inventario_carta_eliminada'     => 'Carta eliminada del inventario',

    // --- Tradeos ---
    'tradeo_no_encontrado'  => 'Tradeo no encontrado',
    'tradeo_publicado'      => 'Tradeo publicado correctamente',
    'tradeo_actualizado'    => 'Tradeo actualizado correctamente',
    'tradeo_eliminado'      => 'Tradeo eliminado correctamente',
    'tradeo_no_disponible'  => 'Este tradeo ya no está disponible',
    'tradeo_propio'         => 'No puedes aceptar tu propio tradeo',
    'tradeo_sin_permiso_editar'  => 'No tienes permiso para modificar este tradeo',
    'tradeo_sin_permiso_borrar'  => 'No tienes permiso para eliminar este tradeo',
    'tradeo_no_publicado'   => 'No se pudo publicar el tradeo.',
    'tradeo_error_eliminar' => 'Error al eliminar el tradeo',

    // El nombre de la carta va como parámetro y NO se traduce: es un dato,
    // y hasta la fase 4 la BD solo lo tiene en un idioma.
    'no_tienes_carta'    => 'No tienes la carta requerida: :carta',
    'no_tienes_ofrecidas' => 'No tienes en tu inventario alguna de las cartas que intentas ofrecer.',

    'intercambio_completado' => 'Intercambio completado correctamente',
    'intercambio_fallido'    => 'No se pudo completar el intercambio.',

];
