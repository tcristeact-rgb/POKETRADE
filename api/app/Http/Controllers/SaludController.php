<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

// --- GET /api/health ---
//
// El endpoint más barato que puede haber: no toca la base de datos, no llama a
// TCGdex, no lee la caché. Responde y ya. Si esto contesta, el contenedor está
// levantado y sirviendo — que es justo lo único que hace falta saber.
//
// Para qué sirve:
//
//   · Monitorización. Un 200 aquí significa "PHP arrancó, Laravel arrancó y las
//     rutas están montadas". Si además quisiéramos saber si la BD responde,
//     tendría que ser OTRO endpoint: mezclarlos hace que una caída de Supabase
//     parezca una caída de la API, y son cosas distintas.
//
//   · Despertar el servicio. Render (plan free) lo apaga tras un rato sin
//     tráfico, y la primera visita después paga el arranque entero. Un ping
//     periódico contra esta ruta lo mantiene en pie por el coste más bajo
//     posible. (NO está montado: ver el README, que explica cómo hacerlo y
//     cuántas horas gratuitas se lleva por delante.)
//
// Laravel 11 ya trae /up (ver bootstrap/app.php), pero eso dispara el evento
// DiagnosingHealth y vive fuera del grupo de la API: sin CORS, así que un
// navegador no puede consultarlo. Esta ruta sí.
class SaludController extends Controller
{
    // Invocable, no una closure: `php artisan route:cache` no sabe serializar
    // closures y falla con una sola que haya en toda la aplicación.
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok'   => true,
            'hora' => now()->toIso8601String(),
        ]);
    }
}
