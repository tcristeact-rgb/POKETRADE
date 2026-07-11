<?php

namespace App\Services;

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

    // Los valores de tipo son distintos por idioma en TCGdex; esta
    // tabla permite repetir en el catálogo inglés una búsqueda hecha
    // con el tipo en español (sets antiguos nunca traducidos)
    private const TIPOS_EN = [
        'Agua'     => 'Water',
        'Dragón'   => 'Dragon',
        'Fuego'    => 'Fire',
        'Hada'     => 'Fairy',
        'Incolora' => 'Colorless',
        'Lucha'    => 'Fighting',
        'Metálica' => 'Metal',
        'Oscura'   => 'Darkness',
        'Planta'   => 'Grass',
        'Psíquico' => 'Psychic',
        'Rayo'     => 'Lightning',
    ];

    // Campos que se completan desde "en" si "es" los trae vacíos.
    // Solo los tres primeros disparan la petición de fallback: los
    // demás pueden faltar legítimamente (Entrenador/Energía sin tipos,
    // cartas sin descripción) y no justifican una petición extra.
    private const CAMPOS_FALLBACK  = ['name', 'rarity', 'image', 'illustrator', 'description'];
    private const CAMPOS_CRITICOS  = ['name', 'rarity', 'image'];

    // --- Tipos y rarezas del TCG, localizados ---
    // GET /v2/{lang}/types · GET /v2/{lang}/rarities → ["Agua", ...]
    // Pueblan los desplegables de filtros del catálogo: los DISTINCT de
    // la BD no sirven porque la mayoría de cartas cacheadas bajo
    // demanda aún no está hidratada (tipo y rareza a NULL).
    public function listarTipos(): ?array
    {
        return $this->get('es', 'types') ?? $this->get('en', 'types');
    }

    public function listarRarezas(): ?array
    {
        return $this->get('es', 'rarities') ?? $this->get('en', 'rarities');
    }

    // --- Búsqueda de cartas por filtros en todo el catálogo ---
    // GET /v2/{lang}/cards?name=&types=&rarity=&set.id=&pagination:...
    // Filtros combinables (AND). Devuelve resúmenes [{id, localId,
    // name, image?}]. Español primero y, si no llena el límite,
    // complemento inglés deduplicado por id (sets nunca traducidos).
    // El tipo se traduce con TIPOS_EN para el catálogo inglés; la
    // rareza no tiene traducción fiable, así que con ese filtro activo
    // no hay complemento. Devuelve null solo si TCGdex no responde.
    public function buscarCartas(array $filtros, int $limite = 60): ?array
    {
        $es = $this->consultarCartas('es', $filtros, $limite);

        $en = null;
        $sinRareza = empty($filtros['rarity']);
        if (($es === null || count($es) < $limite) && $sinRareza) {
            $filtrosEn = $filtros;
            if (!empty($filtrosEn['types'])) {
                $filtrosEn['types'] = self::TIPOS_EN[$filtrosEn['types']] ?? $filtrosEn['types'];
            }
            $en = $this->consultarCartas('en', $filtrosEn, $limite);
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

    // Una consulta de búsqueda en un idioma concreto, con caché corta
    private function consultarCartas(string $idioma, array $filtros, int $limite): ?array
    {
        $params = array_filter($filtros) + [
            'pagination:page'         => 1,
            'pagination:itemsPerPage' => $limite,
        ];

        return $this->get($idioma, 'cards?' . http_build_query($params), self::CACHE_TTL_BUSQUEDA);
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
