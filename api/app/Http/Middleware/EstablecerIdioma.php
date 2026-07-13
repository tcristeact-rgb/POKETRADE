<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// --- Idioma de la respuesta, negociado con el cliente ---
//
// El frontend manda el idioma activo en Accept-Language (lo pone apiFetch()
// en auth.js). Se usa la cabecera estándar y no un ?lang=: es lo que Laravel
// y los proxies ya entienden, y no ensucia todas las URLs de la API.
//
// A partir de aquí, __() y los mensajes de validación salen en ese idioma.
class EstablecerIdioma
{
    // Los idiomas que tenemos traducidos (lang/es, lang/en). Un idioma nuevo
    // se añade aquí y creando su carpeta en lang/.
    private const SOPORTADOS = ['es', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $idioma = $this->negociar($request->header('Accept-Language', ''));

        app()->setLocale($idioma);

        $respuesta = $next($request);

        // Content-Language deja constancia de en qué idioma va la respuesta.
        $respuesta->headers->set('Content-Language', $idioma);

        // Vary le dice a las cachés intermedias que la respuesta depende del
        // idioma pedido; sin ella, un proxy podría servirle a un usuario
        // inglés la respuesta que cacheó para uno español.
        //
        // Se AÑADE (replace: false) en vez de asignar: el middleware de CORS
        // pone su propio Vary: Origin, y con set() el resultado dependería de
        // cuál de los dos escriba el último.
        $respuesta->setVary('Accept-Language', false);

        return $respuesta;
    }

    // Accept-Language: "es-ES,es;q=0.9,en;q=0.8"
    //
    // Se recorren los idiomas por preferencia (q descendente, 1.0 si no hay q)
    // y se devuelve el primero que sepamos hablar. "es-ES" cuenta como "es":
    // solo miramos la etiqueta principal, que es lo que distinguen nuestros
    // diccionarios. Si no coincide ninguno, el idioma por defecto de la app.
    private function negociar(string $cabecera): string
    {
        $preferencias = [];

        foreach (explode(',', $cabecera) as $trozo) {
            $partes = explode(';q=', trim($trozo));
            $codigo = strtolower(trim($partes[0]));

            if ($codigo === '' || $codigo === '*') {
                continue;
            }

            // "es-ES" → "es"
            $principal = substr($codigo, 0, 2);
            $peso      = isset($partes[1]) ? (float) $partes[1] : 1.0;

            // Nos quedamos con el peso más alto de cada idioma: "es-ES;q=0.9,es;q=0.4"
            // es una sola preferencia por "es", la mejor de las dos.
            $preferencias[$principal] = max($preferencias[$principal] ?? 0, $peso);
        }

        arsort($preferencias);

        foreach (array_keys($preferencias) as $idioma) {
            if (in_array($idioma, self::SOPORTADOS, true)) {
                return $idioma;
            }
        }

        return config('app.locale');
    }
}
