<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use App\Models\Serie;
use App\Models\Set;
use App\Services\TcgdexService;
use App\Support\CatalogoTcg;
use App\Support\Idiomas;
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
                return response()->json(['error' => __('mensajes.serie_no_encontrada')], 404);
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
    // La serie llega con sets_count para que el frontend sepa si es una
    // serie de un solo set y arme el breadcrumb sin un nivel que sería
    // un click muerto (llevaría de vuelta a este mismo set).
    public function show($id)
    {
        $set = Set::with(['serie' => fn ($q) => $q->withCount('sets')])
            ->where('tcgdex_id', $id)
            ->when(ctype_digit((string) $id), fn ($q) => $q->orWhere('id', $id))
            ->first();

        if (!$set) {
            return response()->json(['error' => __('mensajes.set_no_encontrado')], 404);
        }

        return response()->json($set);
    }

    // --- Cartas de un set, con cacheo bajo demanda y por idioma ---
    // Endpoint: GET /api/sets/{id}/cartas
    // Acceso: público (sin token)
    // Query params opcionales:
    //   ?nombre=X            → búsqueda parcial insensible a mayúsculas
    //   ?tipo=X&rareza=X     → filtros exactos (ver filtrarPorTipoYRareza)
    //   ?page=N&por_pagina=M → paginación (24 por defecto, máx. 100)
    //
    // El cache-aside es por idioma: la primera visita al set EN CADA IDIOMA
    // descarga ese catálogo de TCGdex (una petición) y lo vuelca a la tabla
    // cartas, cada uno en sus columnas. Las siguientes visitas en ese idioma
    // ya no salen de la BD. No hay backfill: un set que nadie ha abierto en
    // inglés no gasta ni una petición en traducirse.
    //
    // Las cartas entran solo con nombre, número e imagen: el resto lo completa
    // CartaController::show al abrir cada carta.
    public function cartas(Request $request, TcgdexService $tcgdex, $id)
    {
        $set = Set::where('tcgdex_id', $id)
            ->when(ctype_digit((string) $id), fn ($q) => $q->orWhere('id', $id))
            ->first();

        if (!$set) {
            return response()->json(['error' => __('mensajes.set_no_encontrado')], 404);
        }

        $this->cachearSetSiHaceFalta($set, $tcgdex);

        if (!$set->synced_at) {
            return response()->json([
                'error' => __('mensajes.tcgdex_caido'),
            ], 503);
        }

        $query = $set->cartas();

        // Filtro por nombre — en SQL, parcial e insensible a mayúsculas, y
        // buscando en los nombres de todos los idiomas (ver el scope)
        if ($request->filled('nombre')) {
            $query->nombreParecidoA($request->nombre);
        }

        $this->filtrarPorTipoYRareza($query, $set, $tcgdex,
            trim((string) $request->query('tipo')),
            trim((string) $request->query('rareza')));

        // Orden de coleccionista: por longitud y luego alfabético, que
        // ordena bien números ("2" < "10") y deja las cartas secretas y
        // de galería ("TG01"...) tras el set principal; id como desempate
        $porPagina = min(100, max(1, (int) $request->input('por_pagina', 24)));

        $cartas = $query
            ->orderByRaw('LENGTH(numero), numero')
            ->orderBy('id')
            ->paginate($porPagina);

        return response()->json($cartas);
    }

    // Filtro por tipo y rareza sobre las cartas de un set. No puede ir
    // directamente en SQL: la mayoría de cartas cacheadas bajo demanda
    // aún no está hidratada (tipo_key y rareza_key a NULL). En su lugar se
    // pide a TCGdex la lista de cartas del set que cumplen el filtro (una
    // petición, cacheada) y se intersecta por tcgdex_id. Si TCGdex no
    // responde, se degrada al filtro SQL sobre lo ya hidratado.
    //
    // Los filtros llegan como clave canónica ('fire'), no como texto: así el
    // mismo enlace filtrado funciona igual en español y en inglés.
    private function filtrarPorTipoYRareza($query, Set $set, TcgdexService $tcgdex, string $tipo, string $rareza): void
    {
        $tipoKey   = CatalogoTcg::claveTipo($tipo);
        $rarezaKey = CatalogoTcg::claveRareza($rareza);

        if ($tipoKey === null && $rarezaKey === null) {
            return;
        }

        // 500 cubre de sobra el set más grande (~450 cartas)
        $coincidentes = $tcgdex->buscarCartas(array_filter([
            'set.id'     => $set->tcgdex_id,
            'tipo_key'   => $tipoKey,
            'rareza_key' => $rarezaKey,
        ]), 500);

        if ($coincidentes !== null) {
            $query->whereIn('tcgdex_id', collect($coincidentes)->pluck('id'));
            return;
        }

        if ($tipoKey !== null) {
            $query->where('tipo_key', $tipoKey);
        }
        if ($rarezaKey !== null) {
            $query->where('rareza_key', $rarezaKey);
        }
    }

    // Se asegura de que el set esté cacheado en el idioma de la petición.
    private function cachearSetSiHaceFalta(Set $set, TcgdexService $tcgdex): void
    {
        $idioma = Idiomas::activo();

        $declaradas = $set->cacheadoEn($idioma)
            ? null
            : $this->cachearSet($set, $tcgdex, $idioma);

        // Los catálogos que no son el inglés van incompletos: hay sets que en
        // español existen solo como metadatos, sin una sola carta (neo1 declara
        // 111 y no trae ninguna). Si después de cachear siguen faltando filas,
        // las trae el inglés, que es el catálogo completo — y esas cartas se
        // quedan con su nombre inglés, que es mejor que no estar.
        $total = max($declaradas ?? 0, $set->numero_cartas);

        if (!$set->cacheadoEn(TcgdexService::COMPLETO) && $set->cartas()->count() < $total) {
            $this->cachearSet($set, $tcgdex, TcgdexService::COMPLETO);
        }
    }

    // Vuelca el catálogo de UN idioma a la tabla cartas y devuelve cuántas
    // cartas dice TCGdex que tiene el set (que no siempre son las que trae).
    //
    // El upsert por tcgdex_id (índice único) es idempotente y solo toca las
    // columnas de ese idioma: cachear "151" en inglés rellena nombre_en e
    // imagen_en de sus 207 cartas sin rozar el español, ni la rareza, ni el
    // precio, ni la descripción que ya tuvieran.
    private function cachearSet(Set $set, TcgdexService $tcgdex, string $idioma): ?int
    {
        $datos = $tcgdex->obtenerSet($set->tcgdex_id, $idioma);

        // null = TCGdex no contestó. Ni se persiste ni se marca el intento: el
        // set queda sin cachear y la próxima visita vuelve a probar.
        if ($datos === null) {
            return null;
        }

        // Cualquier otra cosa es una respuesta, aunque sea para decir que ese
        // catálogo no tiene el set ([]) o que lo tiene sin cartas: el intento
        // queda anotado y no se repite nunca más.
        $set->marcarCacheadoEn($idioma);

        if (empty($datos['cards'])) {
            return $datos['cardCount']['total'] ?? null;
        }

        $filas = collect($datos['cards'])->map(fn ($carta) => [
            'tcgdex_id'       => $carta['id'],
            "nombre_{$idioma}" => $carta['name'],
            "imagen_{$idioma}" => $carta['image'] ?? null,
            'numero'          => $carta['localId'] ?? null,
            'set_id'          => $set->tcgdex_id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::transaction(function () use ($set, $filas, $idioma) {
            // En bloques de 200 para no exceder el límite de parámetros
            // por consulta de PostgreSQL con los sets más grandes
            $filas->chunk(200)->each(fn ($bloque) => Carta::upsert(
                $bloque->all(),
                ['tcgdex_id'],
                ["nombre_{$idioma}", "imagen_{$idioma}", 'numero', 'set_id', 'updated_at']
            ));

            $set->update([
                'synced_at'     => now(),
                'numero_cartas' => $set->cartas()->count(),
            ]);
        });

        return $datos['cardCount']['total'] ?? null;
    }

    // Resuelve una serie por su ID de TCGdex o su ID interno numérico
    private function buscarSerie(string $id): ?Serie
    {
        return Serie::where('tcgdex_id', $id)
            ->when(ctype_digit($id), fn ($q) => $q->orWhere('id', $id))
            ->first();
    }
}
