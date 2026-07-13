<?php

namespace App\Http\Controllers;

use App\Models\Serie;

class SerieController extends Controller
{
    // --- Listar series del TCG ---
    // Endpoint: GET /api/series
    // Acceso: público (sin token)
    // Devuelve el índice de series para la vista de expansiones, cada una
    // con su nº de sets, ordenadas de más reciente a más antigua según la
    // fecha de lanzamiento de su set más nuevo.
    // Cada serie incluye además set_unico: el tcgdex_id de su único set
    // cuando solo tiene uno (null en el resto). Con él, el catálogo
    // enlaza directamente a las cartas y se ahorra al usuario un grid de
    // una sola tarjeta, que es un click muerto.
    public function index()
    {
        $series = Serie::withCount('sets')
            ->withMax('sets as fecha_ultimo_set', 'fecha_lanzamiento')
            ->with('sets:id,serie_id,tcgdex_id')
            ->get();

        // Ordenamos en memoria (~25 filas): PostgreSQL no permite usar el
        // alias del withMax dentro de una expresión ORDER BY portable, y
        // así además dejamos las series sin fecha al final sin SQL raro
        $ordenadas = $series
            ->sortByDesc(fn ($serie) => $serie->fecha_ultimo_set ?? '')
            ->values();

        $ordenadas->each(function (Serie $serie) {
            $serie->set_unico = $serie->sets_count === 1
                ? $serie->sets->first()?->tcgdex_id
                : null;

            // La lista de sets solo servía para calcular set_unico: fuera
            // de la respuesta, que este endpoint es un índice ligero
            $serie->unsetRelation('sets');
        });

        return response()->json($ordenadas);
    }

    // --- Detalle de una serie con sus sets ---
    // Endpoint: GET /api/series/{id}
    // Acceso: público (sin token)
    // {id} admite el ID de TCGdex (ej: "sv") o el ID interno numérico.
    // Los sets van ordenados de más reciente a más antiguo; los que no
    // tienen fecha, al final (expresión portable SQLite/PostgreSQL).
    public function show($id)
    {
        $serie = Serie::with(['sets' => fn ($q) => $q
                ->orderByRaw('fecha_lanzamiento IS NULL')
                ->orderByDesc('fecha_lanzamiento')])
            ->where('tcgdex_id', $id)
            ->when(ctype_digit((string) $id), fn ($q) => $q->orWhere('id', $id))
            ->first();

        if (!$serie) {
            return response()->json(['error' => __('mensajes.serie_no_encontrada')], 404);
        }

        return response()->json($serie);
    }
}
