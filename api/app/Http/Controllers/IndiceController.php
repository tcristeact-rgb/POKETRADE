<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

// --- GET / ---
//
// La raíz de la API. Antes servía la página de bienvenida por defecto de Laravel:
// 84 KB de la portada de marketing del framework, que es lo que veía cualquiera
// que pegase la URL de la API en el navegador — un desarrollador comprobando que
// está viva, o un reclutador con curiosidad.
//
// Ahora dice lo que es y por dónde se entra. Que es lo que uno espera encontrar.
class IndiceController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'nombre'        => 'PokeTrade API',
            'descripcion'   => 'REST API for the PokeTrade Pokémon TCG trading platform.',
            'documentacion' => 'https://github.com/tcristeact-rgb/POKETRADE',
            'demo'          => 'https://poketrade-beryl.vercel.app',
            'salud'         => url('/api/health'),
            'endpoints'     => [
                'GET /api/series'             => 'Every TCG series, with set counts',
                'GET /api/sets/{id}/cartas'   => 'Cards of a set — caches them on demand',
                'GET /api/cartas'             => 'Card catalog, paginated and filterable',
                'GET /api/cartas/buscar'      => 'Global search across the whole TCG',
                'GET /api/tradeos'            => 'Public trade marketplace',
                'POST /api/auth/login'        => 'JWT authentication',
            ],
            'idiomas' => [
                'soportados' => \App\Support\Idiomas::SOPORTADOS,
                'como'       => 'Send an Accept-Language header. Responses carry Content-Language.',
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
