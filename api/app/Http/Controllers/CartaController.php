<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use App\Rules\ClaveTcgValida;
use App\Services\TcgdexService;
use App\Support\CatalogoTcg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator; // Para validar los datos recibidos

class CartaController extends Controller
{
    // --- Listar cartas del catálogo, paginadas y con filtros ---
    // Endpoint: GET /api/cartas
    // Acceso: público (sin token)
    // Query params opcionales:
    //   ?nombre=X       → búsqueda parcial insensible a mayúsculas
    //   ?tipo=X         → clave canónica del tipo (ej: "fire")
    //   ?rareza=X       → clave canónica de la rareza (ej: "double-rare")
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

        // Filtro por tipo y rareza — por CLAVE canónica, no por el texto
        // traducido: ?tipo=fire funciona igual en cualquier idioma, y un
        // enlace con filtros se puede compartir entre usuarios de idiomas
        // distintos. claveTipo() acepta además el nombre en español o inglés,
        // para no romper los enlaces antiguos (?tipo=Fuego sigue valiendo).
        if ($request->filled('tipo')) {
            $query->where('tipo_key', CatalogoTcg::claveTipo($request->tipo));
        }

        if ($request->filled('rareza')) {
            $query->where('rareza_key', CatalogoTcg::claveRareza($request->rareza));
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
    //
    // Tipos y rarezas son un conjunto CERRADO y pequeño (11 y 40), así que
    // salen de nuestro propio catálogo y ya no de TCGdex. Eso arregla tres
    // cosas de golpe: la lista deja de depender de que una API de terceros
    // responda, sale ya traducida al idioma de la petición, y el español
    // deja de tener rarezas a medio traducir ("Uncommon", "Shiny rare"),
    // que es lo que TCGdex devuelve en su propio catálogo español.
    //
    // Cada entrada es {clave, etiqueta}: la clave viaja en la URL (?tipo=fire)
    // y la etiqueta es lo que se lee en el desplegable.
    public function filtros()
    {
        return response()->json([
            'tipos'   => CatalogoTcg::tipos(),
            'rarezas' => CatalogoTcg::rarezas(),
            'sets'    => Carta::whereNotNull('set_expansion')->distinct()->orderBy('set_expansion')->pluck('set_expansion'),
        ]);
    }

    // --- Cartas destacadas para el hero del home ---
    // Endpoint: GET /api/cartas/destacadas
    // Acceso: público (sin token)
    // Las 4 más caras (precio Cardmarket avg) entre las 200 cartas más
    // recientes de la BD. La ventana va por id descendente y no por
    // created_at porque el cache-aside inserta sets enteros de golpe y
    // los timestamps se agrupan en bloques. La mayoría de cartas
    // recientes aún no está hidratada (sin precio), así que si la
    // ventana no da 4, se completa con las más caras de toda la BD:
    // no es un caso raro, es el camino habitual.
    // Caché de 1 h: congela la selección y ahorra las consultas.
    //
    // El idioma va DENTRO de la clave. Hoy la respuesta es igual en los dos
    // (los nombres de carta aún no están traducidos), pero en cuanto la fase 4
    // sirva nombre_es/nombre_en, una clave sin idioma haría que el primer
    // visitante del home fijase SU idioma para todos los demás durante una
    // hora. Se arregla ahora, que es gratis, y no cuando ya esté roto.
    public function destacadas()
    {
        $cartas = Cache::remember('cartas.destacadas.' . app()->getLocale(), 3600, function () {
            $ventana = Carta::orderByDesc('id')->limit(200)->pluck('id');

            // Recientes con precio, de mayor a menor
            $destacadas = Carta::whereIn('id', $ventana)
                ->whereNotNull('precio_cardmarket')
                ->orderByDesc('precio_cardmarket')
                ->limit(4)
                ->get();

            // Fallback: completar con las más caras del catálogo entero
            // (las recientes conservan la primera posición: son la
            // novedad que el hero quiere enseñar)
            if ($destacadas->count() < 4) {
                $destacadas = $destacadas->concat(
                    Carta::whereNotNull('precio_cardmarket')
                        ->whereNotIn('id', $destacadas->pluck('id'))
                        ->orderByDesc('precio_cardmarket')
                        ->limit(4 - $destacadas->count())
                        ->get()
                );
            }

            return $destacadas->values();
        });

        return response()->json(['data' => $cartas]);
    }

    // --- Búsqueda global en todo el catálogo del TCG ---
    // Endpoint: GET /api/cartas/buscar?q=&tipo=&rareza=
    // Acceso: público (sin token)
    // Consulta TCGdex (no solo lo cacheado en BD) con caché corta de
    // 10 min y tope de 60 resultados. NO persiste nada: los resúmenes
    // se devuelven tal cual, y la fila en BD se crea solo si alguien
    // abre el detalle (show acepta el tcgdex_id). Para las cartas que
    // ya están en BD se incluye su id interno, así el frontend enlaza
    // el detalle igual que en cualquier otro grid.
    public function buscar(Request $request, TcgdexService $tcgdex)
    {
        $q      = trim((string) $request->query('q'));
        $tipo   = trim((string) $request->query('tipo'));
        $rareza = trim((string) $request->query('rareza'));

        if ($q === '' && $tipo === '' && $rareza === '') {
            return response()->json(['error' => __('mensajes.busqueda_sin_filtro')], 422);
        }
        if ($q !== '' && mb_strlen($q) < 2) {
            return response()->json(['error' => __('mensajes.busqueda_corta')], 422);
        }

        // Los filtros llegan como clave canónica; el servicio se encarga de
        // traducirlos al texto que entiende cada catálogo de TCGdex
        $resultados = $tcgdex->buscarCartas(array_filter([
            'name'       => $q,
            'tipo_key'   => CatalogoTcg::claveTipo($tipo),
            'rareza_key' => CatalogoTcg::claveRareza($rareza),
        ]));

        if ($resultados === null) {
            return response()->json([
                'error' => __('mensajes.tcgdex_caido'),
            ], 503);
        }

        // Fuera las cartas de series excluidas por config (Pocket,
        // McDonald's...): TCGdex las devuelve igualmente porque el
        // filtrado es nuestro, no suyo
        $excluidos  = array_flip($tcgdex->setsExcluidos());
        $resultados = array_values(array_filter(
            $resultados,
            fn ($c) => !isset($excluidos[$this->setDeCarta($c)])
        ));

        // IDs internos de las cartas que ya están en la BD (una consulta)
        $locales = Carta::whereIn('tcgdex_id', collect($resultados)->pluck('id'))
            ->pluck('id', 'tcgdex_id');

        // Misma forma que las cartas de la BD (imagen_low/imagen_high
        // montadas aquí) para reutilizar tarjetaCarta() y el lightbox
        $cartas = collect($resultados)->map(fn ($c) => [
            'id'          => $locales[$c['id']] ?? null,
            'tcgdex_id'   => $c['id'],
            'nombre'      => $c['name'],
            'numero'      => $c['localId'] ?? null,
            'imagen_low'  => isset($c['image']) ? "{$c['image']}/low.webp" : null,
            'imagen_high' => isset($c['image']) ? "{$c['image']}/high.webp" : null,
        ])->values();

        return response()->json(['data' => $cartas, 'total' => $cartas->count()]);
    }

    // --- Ver detalle de una carta ---
    // Endpoint: GET /api/cartas/{id}
    // Acceso: público (sin token)
    // Incluye anterior_id / siguiente_id para que el frontend pueda
    // navegar entre cartas del catálogo sin asumir IDs consecutivos.
    public function show($id)
    {
        // Por ID interno (numérico) o por ID de TCGdex (ej: "sv03.5-006",
        // desde la búsqueda global). Si la carta de TCGdex aún no está en
        // BD, se crea aquí bajo demanda: así la búsqueda global no
        // persiste nada y la BD solo crece con cartas realmente abiertas.
        $carta = ctype_digit((string) $id)
            ? Carta::find($id)
            : Carta::firstWhere('tcgdex_id', $id) ?? $this->crearDesdeTcgdex($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => __('mensajes.carta_no_encontrada')], 404);
        }

        // Hidratación perezosa: las cartas cacheadas desde el resumen de
        // un set (cache-aside) solo traen nombre, número e imagen; la
        // primera vez que alguien abre la carta completamos su detalle
        // desde TCGdex y lo persistimos para las visitas siguientes
        if ($carta->tcgdex_id && !$carta->detalle_synced_at) {
            $this->hidratarDetalle($carta);
        }

        // Navegación anterior/siguiente acotada al set de la carta, para
        // recorrer la expansión completa en orden; las cartas sin set
        // (creadas a mano por un admin) navegan por todo el catálogo
        $vecinas = Carta::query()
            ->when($carta->set_id, fn ($q) => $q->where('set_id', $carta->set_id));

        return response()->json(array_merge($carta->toArray(), [
            'anterior_id'  => (clone $vecinas)->where('id', '<', $carta->id)->max('id'),
            'siguiente_id' => (clone $vecinas)->where('id', '>', $carta->id)->min('id'),
        ]));
    }

    // Deriva el ID del set desde el resumen de una carta. El id es
    // "{set}-{localId}" y el set puede contener guiones ("tk-xy-su-4"
    // → set "tk-xy-su"), así que se recorta el sufijo del localId
    private function setDeCarta(array $carta): string
    {
        $localId = (string) ($carta['localId'] ?? '');

        if ($localId !== '' && str_ends_with($carta['id'], "-{$localId}")) {
            return substr($carta['id'], 0, -strlen("-{$localId}"));
        }

        return strtok($carta['id'], '-');
    }

    // Crea la fila de una carta de TCGdex que aún no está en la BD
    // (abierta desde la búsqueda global). Nace ya hidratada: el detalle
    // completo viene en la misma petición que valida que existe.
    private function crearDesdeTcgdex(string $tcgdexId): ?Carta
    {
        $datos = app(TcgdexService::class)->obtenerCarta($tcgdexId);

        if (!$datos || empty($datos['name'])) {
            return null;
        }

        // firstOrCreate por si dos usuarios abren la misma carta a la vez
        // (tcgdex_id tiene índice único)
        return Carta::firstOrCreate(
            ['tcgdex_id' => $datos['id'] ?? $tcgdexId],
            [
                'nombre'            => $datos['name'],
                'tipo_key'          => CatalogoTcg::claveTipo($datos['types'][0] ?? null),
                'rareza_key'        => CatalogoTcg::claveRareza($datos['rarity'] ?? null),
                // El id de TCGdex es "{set}-{numero}": si el detalle no
                // trae el set, se deriva del prefijo
                'set_id'            => $datos['set']['id'] ?? strtok($tcgdexId, '-'),
                'set_expansion'     => $datos['set']['name'] ?? null,
                'numero'            => $datos['localId'] ?? null,
                'imagen_url'        => $datos['image'] ?? null,
                'descripcion'       => $datos['description'] ?? null,
                'ilustrador'        => $datos['illustrator'] ?? null,
                'hp'                => $datos['hp'] ?? null,
                'precio_cardmarket' => $datos['pricing']['cardmarket']['avg']
                                        ?? $datos['pricing']['cardmarket']['trend']
                                        ?? null,
                'detalle_synced_at' => now(),
            ]
        );
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
            'tipo_key'          => CatalogoTcg::claveTipo($datos['types'][0] ?? null) ?? $carta->tipo_key,
            'rareza_key'        => CatalogoTcg::claveRareza($datos['rarity'] ?? null) ?? $carta->rareza_key,
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
        // Solo el nombre es obligatorio, el resto son opcionales.
        // tipo y rareza se aceptan como CLAVE ('fire') o como el nombre en
        // español o inglés: Rule::in acota a lo que el catálogo conoce, así
        // que una rareza inventada se rechaza en vez de acabar en la BD.
        $validacion = Validator::make($request->all(), [
            'nombre'     => 'required|string',
            'tipo'       => ['nullable', 'string', new ClaveTcgValida('tipo')],
            'rareza'     => ['nullable', 'string', new ClaveTcgValida('rareza')],
            'imagen_url' => 'nullable|string',
        ]);

        // Si la validación falla devolvemos el primer error con código 422
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 422);
        }

        // Los campos que no vengan en el request se quedarán como null
        $carta = Carta::create($request->except(['tipo', 'rareza']) + [
            'tipo_key'   => CatalogoTcg::claveTipo($request->tipo),
            'rareza_key' => CatalogoTcg::claveRareza($request->rareza),
        ]);

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
            return response()->json(['error' => __('mensajes.carta_no_encontrada')], 404);
        }

        // Actualizamos solo los campos que vengan en el request
        // Los campos no enviados mantienen su valor actual
        $carta->update($request->except(['tipo', 'rareza']) + array_filter([
            'tipo_key'   => CatalogoTcg::claveTipo($request->tipo),
            'rareza_key' => CatalogoTcg::claveRareza($request->rareza),
        ]));

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
            return response()->json(['error' => __('mensajes.carta_no_encontrada')], 404);
        }

        // Eliminamos la carta de la base de datos
        $carta->delete();

        return response()->json(['mensaje' => __('mensajes.carta_eliminada')]);
    }
}