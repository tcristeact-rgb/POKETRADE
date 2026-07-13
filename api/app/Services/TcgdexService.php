<?php

namespace App\Services;

use App\Support\CatalogoTcg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// --- Cliente de la API pública TCGdex ---
// Documentación: https://tcgdex.dev · Base: https://api.tcgdex.net/v2
//
// Idioma principal "es" con fallback campo a campo a "en" cuando la
// traducción española viene vacía (nombre, rareza, imagen...).
//
// Todas las respuestas se cachean (tabla cache ya migrada) para hacer
// un uso considerado de la API externa y evitar latencia añadida al
// cold start de Render: el seeder y el comando de sincronización son
// los únicos consumidores; las peticiones del frontend nunca llegan
// hasta TCGdex, se sirven siempre desde nuestra BD.
class TcgdexService
{
    private const BASE_URL  = 'https://api.tcgdex.net/v2';
    private const CACHE_TTL = 86400; // 24 horas

    // Caché corta para la búsqueda global: el usuario refina el texto
    // tecleo a tecleo y las mismas consultas se repiten en ráfagas,
    // pero no tiene sentido retenerlas un día entero
    private const CACHE_TTL_BUSQUEDA = 600; // 10 minutos

    // Campos que se completan desde "en" si "es" los trae vacíos.
    // Solo los tres primeros disparan la petición de fallback: los
    // demás pueden faltar legítimamente (Entrenador/Energía sin tipos,
    // cartas sin descripción) y no justifican una petición extra.
    private const CAMPOS_FALLBACK  = ['name', 'rarity', 'image', 'illustrator', 'description'];
    private const CAMPOS_CRITICOS  = ['name', 'rarity', 'image'];

    // --- Búsqueda de cartas por filtros en todo el catálogo ---
    // GET /v2/{lang}/cards?name=&types=&rarity=&set.id=&pagination:...
    //
    // Los filtros llegan con las claves canónicas de PokeTrade (tipo_key,
    // rareza_key) y aquí se traducen al texto que entiende cada catálogo de
    // TCGdex, que es distinto en cada idioma ("Fuego" / "Fire").
    //
    // Se consulta español primero y, si no llena el límite, se complementa con
    // inglés deduplicando por id (los sets antiguos solo existen ahí).
    //
    // Antes, con el filtro de rareza activo NO se hacía el complemento inglés
    // ("la rareza no tiene traducción fiable"). Ahora sí la tiene: el catálogo
    // sabe cómo se llama cada rareza en cada idioma, así que la búsqueda por
    // rareza también alcanza los sets clásicos. Y si una rareza solo existe en
    // el catálogo inglés (las de los sets clásicos), la consulta española se
    // salta directamente en vez de preguntar por un valor que no existe.
    //
    // Devuelve null solo si TCGdex no responde en ninguno de los dos idiomas.
    public function buscarCartas(array $filtros, int $limite = 60): ?array
    {
        $es = $this->consultarCartas('es', $filtros, $limite);

        $en = null;
        if ($es === null || count($es) < $limite) {
            $en = $this->consultarCartas('en', $filtros, $limite);
        }

        if ($es === null && $en === null) {
            return null;
        }

        $cartas    = collect($es ?? []);
        $conocidas = $cartas->pluck('id')->flip();

        foreach ($en ?? [] as $carta) {
            if (!isset($conocidas[$carta['id']])) {
                $cartas->push($carta);
            }
        }

        return $cartas->take($limite)->values()->all();
    }

    // Una consulta de búsqueda en un idioma concreto, con caché corta.
    // Devuelve null (sin llamar a la API) si algún filtro pedido no existe en
    // ese catálogo: preguntar por una rareza que ese idioma no tiene devuelve
    // cero resultados igualmente, y así nos ahorramos la petición.
    private function consultarCartas(string $idioma, array $filtros, int $limite): ?array
    {
        $params = [];

        if (!empty($filtros['name'])) {
            $params['name'] = $filtros['name'];
        }
        if (!empty($filtros['set.id'])) {
            $params['set.id'] = $filtros['set.id'];
        }

        if (!empty($filtros['tipo_key'])) {
            $tipo = CatalogoTcg::tipoTcgdex($filtros['tipo_key'], $idioma);
            if ($tipo === null) {
                return null;
            }
            $params['types'] = $tipo;
        }

        if (!empty($filtros['rareza_key'])) {
            $rareza = CatalogoTcg::rarezaTcgdex($filtros['rareza_key'], $idioma);
            if ($rareza === null) {
                return null;
            }
            $params['rarity'] = $rareza;
        }

        $params += [
            'pagination:page'         => 1,
            'pagination:itemsPerPage' => $limite,
        ];

        return $this->get($idioma, 'cards?' . http_build_query($params), self::CACHE_TTL_BUSQUEDA);
    }

    // --- IDs de los sets de las series excluidas por config ---
    // Para filtrar la búsqueda global. No vale con mirar el prefijo del
    // id: los sets de Pocket no llevan el de su serie ("A1", "P-A"...),
    // así que se resuelven desde el detalle de cada serie excluida
    // (1 petición por serie, cacheada 24 h). El catálogo inglés es el
    // más completo; el español queda de respaldo.
    public function setsExcluidos(): array
    {
        $ids = [];

        foreach (config('tcgdex.series_excluidas', []) as $serieId) {
            $detalle = $this->obtenerSerie($serieId, 'en') ?? $this->obtenerSerie($serieId, 'es');

            foreach ($detalle['sets'] ?? [] as $set) {
                $ids[] = $set['id'];
            }
        }

        return $ids;
    }

    // --- Listado de todas las series del TCG en un idioma ---
    // GET /v2/{lang}/series → [{ id, name }]
    // Sin fallback es→en aquí: el catálogo "es" de TCGdex omite series y
    // sets antiguos que nunca se tradujeron (Gym, e-Card, Base 4 y 5...),
    // así que el comando tcgdex:sync-sets recorre ambos idiomas y combina:
    // español preferido, inglés solo para completar los huecos.
    public function listarSeries(string $idioma = 'es'): ?array
    {
        return $this->get($idioma, 'series');
    }

    // --- Detalle de una serie con sus sets resumidos ---
    // GET /v2/{lang}/series/{id} → { id, name, logo, sets: [{id, name,
    //   logo, symbol, cardCount: {total, official}}] }
    public function obtenerSerie(string $serieId, string $idioma = 'es'): ?array
    {
        return $this->get($idioma, "series/{$serieId}");
    }

    // --- Detalle de un set con su lista de cartas resumidas ---
    // GET /v2/{lang}/sets/{id} → { id, name, logo, symbol, releaseDate,
    //   cardCount, cards: [{id, localId, name, image}] }
    //
    // Ojo: en el catálogo español algunos sets existen solo como
    // metadatos, con la lista de cartas vacía o incompleta (p. ej. neo1
    // declara 111 cartas y no trae ninguna). Si la lista española no
    // alcanza el total declarado, usamos la versión inglesa; y si esa
    // tampoco responde, devolvemos la española (los metadatos siguen
    // sirviendo para el índice de sets).
    public function obtenerSet(string $setId): ?array
    {
        $set = $this->get('es', "sets/{$setId}");

        $total = $set['cardCount']['total'] ?? 0;
        if (!$set || count($set['cards'] ?? []) < $total) {
            return $this->get('en', "sets/{$setId}") ?? $set;
        }

        // Fallback por campo: el detalle español muchas veces no trae
        // logo ni símbolo aunque el inglés sí (mismo patrón que en las
        // cartas). La petición extra queda en el caché de 24 h
        if (empty($set['logo']) || empty($set['symbol'])) {
            $en = $this->get('en', "sets/{$setId}");
            foreach (['logo', 'symbol'] as $campo) {
                if (empty($set[$campo]) && !empty($en[$campo])) {
                    $set[$campo] = $en[$campo];
                }
            }
        }

        return $set;
    }

    // --- Detalle completo de una carta, con fallback es→en ---
    // GET /v2/{lang}/cards/{id}
    public function obtenerCarta(string $cartaId): ?array
    {
        $carta = $this->get('es', "cards/{$cartaId}");

        if (!$carta) {
            return $this->get('en', "cards/{$cartaId}");
        }

        // Si falta algún campo crítico en español, pedimos la versión
        // inglesa y completamos solo los huecos
        $faltanCriticos = collect(self::CAMPOS_CRITICOS)
            ->contains(fn ($campo) => empty($carta[$campo]));

        if ($faltanCriticos) {
            $en = $this->get('en', "cards/{$cartaId}");
            foreach (self::CAMPOS_FALLBACK as $campo) {
                if (empty($carta[$campo]) && !empty($en[$campo])) {
                    $carta[$campo] = $en[$campo];
                }
            }
        }

        return $carta;
    }

    // --- Listado de todos los sets disponibles ---
    public function listarSets(): ?array
    {
        return $this->get('es', 'sets') ?? $this->get('en', 'sets');
    }

    // Petición GET con reintentos, timeout y caché. Devuelve null si la
    // API no responde o da error: los errores no se cachean, así el
    // siguiente intento vuelve a preguntar.
    private function get(string $idioma, string $ruta, int $ttl = self::CACHE_TTL): ?array
    {
        return Cache::remember(
            "tcgdex:{$idioma}:{$ruta}",
            $ttl,
            function () use ($idioma, $ruta) {
                try {
                    $res = Http::retry(3, 300, throw: false)
                        ->timeout(15)
                        ->get(self::BASE_URL . "/{$idioma}/{$ruta}");

                    return $res->successful() ? $res->json() : null;
                } catch (\Throwable) {
                    return null;
                }
            }
        );
    }
}
