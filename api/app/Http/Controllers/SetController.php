<?php

namespace App\Http\Controllers;

use App\Models\Serie;
use App\Models\Set;
use Illuminate\Http\Request;

class SetController extends Controller
{
    // --- Listar sets de expansión ---
    // Endpoint: GET /api/sets
    // Acceso: público (sin token)
    // Query params opcionales:
    //   ?serie=X → solo los sets de esa serie (ID de TCGdex o interno)
    // Ordenados de más reciente a más antiguo; los sets sin fecha de
    // lanzamiento, al final (expresión portable SQLite/PostgreSQL).
    public function index(Request $request)
    {
        $query = Set::with('serie')
            ->orderByRaw('fecha_lanzamiento IS NULL')
            ->orderByDesc('fecha_lanzamiento');

        if ($request->has('serie')) {
            $serie = $this->buscarSerie($request->serie);

            if (!$serie) {
                return response()->json(['error' => 'Serie no encontrada'], 404);
            }

            $query->where('serie_id', $serie->id);
        }

        return response()->json($query->get());
    }

    // --- Detalle de un set (sin sus cartas) ---
    // Endpoint: GET /api/sets/{id}
    // Acceso: público (sin token)
    // {id} admite el ID de TCGdex (ej: "sv03.5") o el ID interno numérico.
    // Las cartas del set se piden aparte (GET /api/sets/{id}/cartas), que
    // es la llamada que dispara el cacheo bajo demanda.
    public function show($id)
    {
        $set = Set::with('serie')
            ->where('tcgdex_id', $id)
            ->when(ctype_digit((string) $id), fn ($q) => $q->orWhere('id', $id))
            ->first();

        if (!$set) {
            return response()->json(['error' => 'Set no encontrado'], 404);
        }

        return response()->json($set);
    }

    // Resuelve una serie por su ID de TCGdex o su ID interno numérico
    private function buscarSerie(string $id): ?Serie
    {
        return Serie::where('tcgdex_id', $id)
            ->when(ctype_digit($id), fn ($q) => $q->orWhere('id', $id))
            ->first();
    }
}
