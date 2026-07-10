<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use App\Models\Serie;
use App\Models\Set;
use App\Services\TcgdexService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // --- Cartas de un set, con cacheo bajo demanda (cache-aside) ---
    // Endpoint: GET /api/sets/{id}/cartas
    // Acceso: público (sin token)
    // Query params opcionales:
    //   ?page=N&por_pagina=M → paginación (24 por defecto, máx. 100)
    //
    // La primera visita al set descarga su lista de cartas de TCGdex (una
    // sola petición) y la persiste en la tabla cartas dentro de una
    // transacción; synced_at se marca solo al final, así el set nunca
    // queda a medio cachear. Las visitas siguientes sirven desde la BD
    // sin tocar la API externa. Las cartas entran solo con nombre, número
    // e imagen: el resto lo completa CartaController::show al abrir cada
    // carta (hidratación perezosa).
    public function cartas(Request $request, TcgdexService $tcgdex, $id)
    {
        $set = Set::where('tcgdex_id', $id)
            ->when(ctype_digit((string) $id), fn ($q) => $q->orWhere('id', $id))
            ->first();

        if (!$set) {
            return response()->json(['error' => 'Set no encontrado'], 404);
        }

        if (!$set->synced_at) {
            $datos = $tcgdex->obtenerSet($set->tcgdex_id);

            if (!$datos || empty($datos['cards'])) {
                return response()->json([
                    'error' => 'El catálogo externo (TCGdex) no responde ahora mismo. Inténtalo de nuevo en unos minutos.',
                ], 503);
            }

            $this->cachearCartas($set, $datos['cards']);
        }

        // Orden de coleccionista: por longitud y luego alfabético, que
        // ordena bien números ("2" < "10") y deja las cartas secretas y
        // de galería ("TG01"...) tras el set principal; id como desempate
        $porPagina = min(100, max(1, (int) $request->input('por_pagina', 24)));

        $cartas = $set->cartas()
            ->orderByRaw('LENGTH(numero), numero')
            ->orderBy('id')
            ->paginate($porPagina);

        return response()->json($cartas);
    }

    // Persiste la lista de cartas del set en una transacción: o entran
    // todas y el set queda marcado como sincronizado, o no entra ninguna.
    // El upsert por tcgdex_id (índice único) hace la operación idempotente
    // y solo toca los campos del resumen: si una carta ya estaba en la BD
    // con detalle completo (seeder o hidratación), su rareza, precio y
    // descripción se conservan.
    private function cachearCartas(Set $set, array $cartas): void
    {
        $filas = collect($cartas)->map(fn ($carta) => [
            'tcgdex_id'     => $carta['id'],
            'nombre'        => $carta['name'],
            'numero'        => $carta['localId'] ?? null,
            'imagen_url'    => $carta['image'] ?? null,
            'set_id'        => $set->tcgdex_id,
            'set_expansion' => $set->nombre,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::transaction(function () use ($set, $filas) {
            // En bloques de 200 para no exceder el límite de parámetros
            // por consulta de PostgreSQL con los sets más grandes
            $filas->chunk(200)->each(fn ($bloque) => Carta::upsert(
                $bloque->all(),
                ['tcgdex_id'],
                ['nombre', 'numero', 'imagen_url', 'set_id', 'set_expansion', 'updated_at']
            ));

            $set->update([
                'synced_at'     => now(),
                'numero_cartas' => $filas->count(),
            ]);
        });
    }

    // Resuelve una serie por su ID de TCGdex o su ID interno numérico
    private function buscarSerie(string $id): ?Serie
    {
        return Serie::where('tcgdex_id', $id)
            ->when(ctype_digit($id), fn ($q) => $q->orWhere('id', $id))
            ->first();
    }
}
