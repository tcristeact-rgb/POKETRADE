<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// --- Respuestas del catálogo que la CDN puede guardar ---
//
// El frontend llega a la API a través de un rewrite de Vercel (/api/* → Render),
// así que la CDN de Vercel está en medio de cada petición. Con estas cabeceras
// sirve desde el borde lo que ya vio, sin despertar a Render ni cruzar hasta
// Supabase:
//
//   max-age=60                   → el navegador lo reutiliza un minuto
//   s-maxage=3600                → la CDN, una hora
//   stale-while-revalidate=86400 → pasada la hora, la CDN sigue sirviendo la
//                                  copia vieja al instante mientras pide la nueva
//
// Solo se marcan las respuestas 2xx de un GET. Un 404 o un 503 de TCGdex caído
// no puede quedarse congelado una hora en la CDN: se queda con el
// "no-cache, private" de siempre y el siguiente intento vuelve a preguntar.
//
// El idioma no hace falta tratarlo aquí: EstablecerIdioma ya añade
// Vary: Accept-Language, que separa la variante española de la inglesa.
//
// Se aplica ruta a ruta en routes/api.php, solo a las de solo lectura del
// catálogo. Lo que depende del usuario o cambia con su actividad (tradeos,
// inventario, las cartas aleatorias de la portada) no lo lleva.
class CachePublica
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if ($request->isMethod('GET') && $respuesta->isSuccessful()) {
            $respuesta->setPublic();

            // Un controlador que ya decidió cuánto guarda el navegador manda él
            // (/cartas/nombres: 24 h con ETag). Aquí solo se pone el valor por defecto
            if (!$respuesta->headers->hasCacheControlDirective('max-age')) {
                $respuesta->setMaxAge(60);
            }

            $respuesta->setSharedMaxAge(3600);
            $respuesta->setStaleWhileRevalidate(86400);
        }

        return $respuesta;
    }
}
