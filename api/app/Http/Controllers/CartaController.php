<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use App\Services\TcgdexService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // Para validar los datos recibidos

class CartaController extends Controller
{
    // --- Listar cartas del catálogo, paginadas y con filtros ---
    // Endpoint: GET /api/cartas
    // Acceso: público (sin token)
    // Query params opcionales:
    //   ?nombre=X       → búsqueda parcial insensible a mayúsculas
    //   ?tipo=X         → filtro exacto (ej: "Fuego")
    //   ?rareza=X       → filtro exacto (ej: "Rara Doble")
    //   ?set=X          → filtro exacto por nombre de set (ej: "151")
    //   ?orden=recientes→ más nuevas primero (para novedades del home)
    //   ?page=N&por_pagina=M → paginación (24 por defecto, máx. 100)
    // Respuesta: paginador estándar de Laravel
    //   { data: [...], total, current_page, last_page, per_page, ... }
    public function index(Request $request)
    {
        // Iniciamos una query base sobre la tabla cartas
        // Iremos añadiendo filtros dinámicamente según los parámetros recibidos
        $query = Carta::query();

        // Filtro por nombre — búsqueda parcial e insensible a mayúsculas
        // Ejemplo: ?nombre=char → devuelve Charizard
        // PostgreSQL distingue mayúsculas con LIKE, por eso en ese motor
        // usamos ILIKE (insensible). SQLite/MySQL ya son insensibles con
        // LIKE y no soportan ILIKE, así que ahí mantenemos LIKE.
        if ($request->has('nombre')) {
            $operadorLike = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('nombre', $operadorLike, '%' . $request->nombre . '%');
        }

        // Filtro por tipo — búsqueda exacta
        // Ejemplo: ?tipo=Fuego → devuelve solo cartas de tipo Fuego
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        // Filtro por rareza — búsqueda exacta
        // Ejemplo: ?rareza=Rara Doble → devuelve solo esa rareza
        if ($request->has('rareza')) {
            $query->where('rareza', $request->rareza);
        }

        // Filtro por set de expansión — búsqueda exacta
        // Ejemplo: ?set=151 → devuelve solo cartas del set "151"
        if ($request->has('set')) {
            $query->where('set_expansion', $request->set);
        }

        // Orden: por defecto el orden natural del catálogo (id ascendente);
        // ?orden=recientes → últimas añadidas primero (novedades del home)
        // ?orden=precio    → más caras primero (destacadas); las cartas sin
        //                    precio van al final (expresión portable
        //                    SQLite/PostgreSQL, sin NULLS LAST)
        if ($request->orden === 'recientes') {
            $query->orderByDesc('id');
        } elseif ($request->orden === 'precio') {
            $query->orderByRaw('precio_cardmarket IS NULL')
                  ->orderByDesc('precio_cardmarket');
        } else {
            $query->orderBy('id');
        }

        // Paginamos para no enviar todo el catálogo de golpe.
        // por_pagina acotado entre 1 y 100 para proteger el backend.
        $porPagina = min(100, max(1, (int) $request->input('por_pagina', 24)));

        return response()->json($query->paginate($porPagina));
    }

    // --- Valores disponibles para los filtros del catálogo ---
    // Endpoint: GET /api/cartas/filtros
    // Acceso: público (sin token)
    // Devuelve los valores distintos presentes en la BD para que el
    // frontend construya los <select> sin hardcodear rarezas/tipos.
    public function filtros()
    {
        return response()->json([
            'tipos'   => Carta::whereNotNull('tipo')->distinct()->orderBy('tipo')->pluck('tipo'),
            'rarezas' => Carta::whereNotNull('rareza')->distinct()->orderBy('rareza')->pluck('rareza'),
            'sets'    => Carta::whereNotNull('set_expansion')->distinct()->orderBy('set_expansion')->pluck('set_expansion'),
        ]);
    }

    // --- Ver detalle de una carta ---
    // Endpoint: GET /api/cartas/{id}
    // Acceso: público (sin token)
    // Incluye anterior_id / siguiente_id para que el frontend pueda
    // navegar entre cartas del catálogo sin asumir IDs consecutivos.
    public function show($id)
    {
        // Buscamos la carta por su ID
        $carta = Carta::find($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        // Hidratación perezosa: las cartas cacheadas desde el resumen de
        // un set (cache-aside) solo traen nombre, número e imagen; la
        // primera vez que alguien abre la carta completamos su detalle
        // desde TCGdex y lo persistimos para las visitas siguientes
        if ($carta->tcgdex_id && !$carta->detalle_synced_at) {
            $this->hidratarDetalle($carta);
        }

        return response()->json(array_merge($carta->toArray(), [
            'anterior_id'  => Carta::where('id', '<', $carta->id)->max('id'),
            'siguiente_id' => Carta::where('id', '>', $carta->id)->min('id'),
        ]));
    }

    // Completa el detalle de la carta desde TCGdex (rareza, tipo, precio,
    // descripción...). Si la API externa no responde, no pasa nada: se
    // sirve lo que haya en la BD y la marca queda a null para reintentar
    // en la próxima visita. Mismo mapeo de campos que el comando
    // cartas:sincronizar-tcgdex.
    private function hidratarDetalle(Carta $carta): void
    {
        $datos = app(TcgdexService::class)->obtenerCarta($carta->tcgdex_id);

        if (!$datos) {
            return;
        }

        $carta->update([
            'nombre'            => $datos['name'] ?? $carta->nombre,
            'tipo'              => $datos['types'][0] ?? $carta->tipo,
            'rareza'            => $datos['rarity'] ?? $carta->rareza,
            'numero'            => $datos['localId'] ?? $carta->numero,
            'imagen_url'        => $datos['image'] ?? $carta->imagen_url,
            'descripcion'       => $datos['description'] ?? $carta->descripcion,
            'ilustrador'        => $datos['illustrator'] ?? $carta->ilustrador,
            'hp'                => $datos['hp'] ?? $carta->hp,
            'precio_cardmarket' => $datos['pricing']['cardmarket']['avg']
                                    ?? $datos['pricing']['cardmarket']['trend']
                                    ?? $carta->precio_cardmarket,
            'detalle_synced_at' => now(),
        ]);
    }

    // --- Crear una nueva carta ---
    // Endpoint: POST /api/cartas
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    public function store(Request $request)
    {
        // Validamos los datos recibidos
        // Solo el nombre es obligatorio, el resto son opcionales
        $validacion = Validator::make($request->all(), [
            'nombre'     => 'required|string',  // Obligatorio
            'tipo'       => 'nullable|string',  // Opcional
            'rareza'     => 'nullable|string',  // Opcional
            'imagen_url' => 'nullable|string',  // Opcional
        ]);

        // Si la validación falla devolvemos el primer error con código 422
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 422);
        }

        // Creamos la carta con todos los datos recibidos
        // Los campos que no vengan en el request se quedarán como null
        $carta = Carta::create($request->all());

        // Devolvemos 201 (creado) con los datos de la carta creada
        return response()->json($carta, 201);
    }

    //NO IMPLEMENTADO EN LA VERSION FINAL
    // --- Actualizar una carta existente ---
    // Endpoint: PUT /api/cartas/{id}
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    public function update(Request $request, $id)
    {
        // Buscamos la carta por su ID
        $carta = Carta::find($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        // Actualizamos solo los campos que vengan en el request
        // Los campos no enviados mantienen su valor actual
        $carta->update($request->all());

        // Devolvemos la carta actualizada
        return response()->json($carta);
    }

    //NO IMPLEMENTADO EN LA VERSION FINAL
    // --- Eliminar una carta ---
    // Endpoint: DELETE /api/cartas/{id}
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    public function destroy($id)
    {
        // Buscamos la carta por su ID
        $carta = Carta::find($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        // Eliminamos la carta de la base de datos
        $carta->delete();

        return response()->json(['mensaje' => 'Carta eliminada correctamente']);
    }
}