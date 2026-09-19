<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use App\Models\Set;
use App\Services\HidratadorDeCartas;
use App\Services\SelectorDeDestacadas;
use App\Services\TcgdexService;
use App\Support\CatalogoTcg;
use App\Support\Idiomas;
use App\Support\Rarezas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CartaController extends Controller
{
    public function __construct(
        private TcgdexService $tcgdex,
        private HidratadorDeCartas $hidratador,
        private SelectorDeDestacadas $selector,
    ) {
    }

    // --- Listar cartas del catálogo, paginadas y con filtros ---
    // Endpoint: GET /api/cartas
    // Acceso: público (sin token)
    // Query params opcionales:
    //   ?nombre=X       → búsqueda parcial insensible a mayúsculas
    //   ?tipo=X         → clave canónica del tipo (ej: "fire")
    //   ?rareza=X       → clave canónica de la rareza (ej: "double-rare")
    //   ?set=X          → ID del set en TCGdex (ej: "sv03.5")
    //   ?orden=recientes→ más nuevas primero (para novedades del home)
    //   ?page=N&por_pagina=M → paginación (24 por defecto, máx. 100)
    // Respuesta: paginador estándar de Laravel
    //   { data: [...], total, current_page, last_page, per_page, ... }
    public function index(Request $request)
    {
        // Iniciamos una query base sobre la tabla cartas
        // Iremos añadiendo filtros dinámicamente según los parámetros recibidos
        $query = Carta::query();

        // Filtro por nombre — parcial, insensible a mayúsculas y en los nombres
        // de TODOS los idiomas (ver el scope en el modelo): quien navega en
        // inglés también encuentra una carta que solo se ha cacheado en español
        if ($request->filled('nombre')) {
            $query->nombreParecidoA($request->nombre);
        }

        // Filtro por tipo y rareza — por CLAVE canónica, no por el texto
        // traducido: ?tipo=fire funciona igual en cualquier idioma, y un
        // enlace con filtros se puede compartir entre usuarios de idiomas
        // distintos. claveTipo() acepta además el nombre en español o inglés,
        // para no romper los enlaces antiguos (?tipo=Fuego sigue valiendo).
        if ($request->filled('tipo')) {
            $query->where('tipo_key', CatalogoTcg::claveTipo($request->tipo));
        }

        // La rareza se filtra por CATEGORÍA de la taxonomía cerrada
        // (?rareza=ultra_rara), que agrupa varias claves finas de TCGdex.
        // Ver el scope en el modelo.
        if ($request->filled('rareza')) {
            $query->deRareza(Rarezas::normalizar($request->rareza));
        }

        // Filtro por set — por su ID de TCGdex, no por su nombre: el nombre
        // depende del idioma ("151" da igual, pero "Cénit Supremo" no es
        // "Silver Tempest"), y el ID vale en cualquiera. Ejemplo: ?set=sv03.5
        if ($request->filled('set')) {
            $query->where('set_id', $request->set);
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
    // Tipos y rarezas son un conjunto CERRADO y pequeño (11 tipos, 10
    // categorías de rareza), así que salen de nuestro propio catálogo y ya
    // no de TCGdex: la lista no depende de que una API de terceros responda
    // y sale ya traducida al idioma de la petición.
    //
    // Cada entrada es {clave, etiqueta}: la clave viaja en la URL (?tipo=fire)
    // y la etiqueta es lo que se lee en el desplegable. Las rarezas llevan
    // además su símbolo, van en el orden canónico de la taxonomía (de menor
    // a mayor rareza) y solo las que existen en el catálogo, como los sets.
    public function filtros()
    {
        $sets = Set::whereHas('cartas')->get()
            ->map(fn (Set $set) => ['clave' => $set->tcgdex_id, 'etiqueta' => $set->nombre])
            ->sortBy('etiqueta', SORT_LOCALE_STRING)
            ->values();

        return response()->json([
            'tipos'   => CatalogoTcg::tipos(),
            'rarezas' => CatalogoTcg::rarezas(),
            'sets'    => $sets,
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
    // Si ni así hay 4, se completan EN VIVO desde TCGdex (ver
    // completarDesdeTcgdex): el catálogo no se siembra, así que una BD
    // recién creada no tiene ni una carta con precio, y la portada es lo
    // primero que ve quien abre la demo. Un hero en blanco no vale.
    //
    // Caché de 1 h: congela la selección y ahorra las consultas. Pero un
    // resultado VACÍO no se guarda nunca: si TCGdex no contestó, la
    // siguiente petición vuelve a intentarlo, igual que hace
    // TcgdexService::get() con sus null. Por eso es get/put y no remember.
    //
    // El idioma va DENTRO de la clave: si no, el primer visitante del home le
    // fijaría SU idioma a todos los demás durante una hora.
    //
    // Y lo que se guarda son los datos ya resueltos (toArray), no los modelos:
    // un modelo serializado arrastra las columnas que tenía el día que se
    // guardó, así que el primer cambio de esquema deja el hero en blanco
    // durante una hora, en producción y sin avisar. Los datos planos no
    // envejecen así.
    public function destacadas()
    {
        $clave  = 'cartas.destacadas.' . app()->getLocale();
        $cartas = Cache::get($clave);

        if ($cartas === null) {
            $cartas = $this->selector->seleccionar(Idiomas::activo());

            if ($cartas !== []) {
                Cache::put($clave, $cartas, 3600);
            }
        }

        return response()->json(['data' => $cartas]);
    }

    // --- Índice de nombres para el autocompletado ---
    // Endpoint: GET /api/cartas/nombres?idioma=es|en
    // Acceso: público (sin token)
    // Respuesta: array de nombres únicos y ordenados. Cacheable 24 h en el
    // navegador (Cache-Control + ETag): el frontend lo baja una vez por sesión
    // al primer foco del buscador y filtra en memoria.
    public function nombres(Request $request)
    {
        $idioma = $request->query('idioma', Idiomas::activo());

        if (!Idiomas::soportado($idioma)) {
            return response()->json(['error' => __('mensajes.idioma_no_soportado')], 422);
        }

        $nombres = $this->tcgdex->nombresDeCartas($idioma);

        if ($nombres === null) {
            return response()->json(['error' => __('mensajes.tcgdex_caido')], 503);
        }

        return response()->json($nombres)
            ->setPublic()
            ->setMaxAge(86400)
            ->setEtag(md5(json_encode($nombres)));
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
    public function buscar(Request $request)
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
        $resultados = $this->tcgdex->buscarCartas(array_filter([
            'name'     => $q,
            'tipo_key' => CatalogoTcg::claveTipo($tipo),
            'rareza'   => Rarezas::normalizar($rareza),
        ]));

        if ($resultados === null) {
            return response()->json([
                'error' => __('mensajes.tcgdex_caido'),
            ], 503);
        }

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
            : Carta::firstWhere('tcgdex_id', $id) ?? $this->hidratador->crear($id, Idiomas::activo());

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => __('mensajes.carta_no_encontrada')], 404);
        }

        // Hidratación perezosa, y por idioma: las cartas cacheadas desde el
        // resumen de un set solo traen nombre, número e imagen; la primera vez
        // que alguien abre la carta EN UN IDIOMA completamos su detalle desde
        // ese catálogo de TCGdex y lo persistimos para las visitas siguientes
        if ($carta->tcgdex_id && !$carta->detalladoEn(Idiomas::activo())) {
            $this->hidratador->hidratar($carta, Idiomas::activo());
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
}
