<?php

namespace App\Services;

use App\Support\CatalogoTcg;
use App\Support\Idiomas;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// --- Cliente de la API pública TCGdex ---
// Documentación: https://tcgdex.dev · Base: https://api.tcgdex.net/v2
//
// TCGdex sirve un catálogo por idioma: /es/cards y /en/cards devuelven las
// MISMAS cartas con el nombre, el tipo, la rareza y la ilustración traducidos.
// Este servicio habla siempre de UN catálogo concreto: quien llama dice cuál.
// El fallback es→en ya no vive aquí — vive en las columnas por idioma de la BD,
// donde un hueco se puede rellenar más tarde en vez de tener que adivinarlo
// ahora. Aquí solo queda el fallback de los assets neutros (logo y símbolo del
// set), que no llevan texto y valen igual en cualquier idioma.
//
// Todas las respuestas se cachean (tabla cache ya migrada) para hacer un uso
// considerado de la API externa y evitar latencia añadida al cold start de
// Render. El caché es por idioma: la clave lo lleva dentro.
class TcgdexService
{
    private const BASE_URL  = 'https://api.tcgdex.net/v2';
    private const CACHE_TTL = 86400; // 24 horas

    // Caché corta para la búsqueda global: el usuario refina el texto
    // tecleo a tecleo y las mismas consultas se repiten en ráfagas,
    // pero no tiene sentido retenerlas un día entero
    private const CACHE_TTL_BUSQUEDA = 600; // 10 minutos

    // El catálogo inglés es el completo: tiene todos los sets, incluidos los
    // clásicos que nunca se tradujeron. Es el respaldo de todo lo demás.
    public const COMPLETO = 'en';

    // --- Búsqueda de cartas por filtros en todo el catálogo ---
    // GET /v2/{lang}/cards?name=&types=&rarity=&set.id=&pagination:...
    //
    // Los filtros llegan con las claves canónicas de PokeTrade (tipo_key,
    // rareza_key) y aquí se traducen al texto que entiende cada catálogo de
    // TCGdex, que es distinto en cada idioma ("Fuego" / "Fire").
    //
    // Se consulta el catálogo del idioma activo y, si no llena el límite, se
    // complementa con el inglés deduplicando por id: los sets clásicos solo
    // existen ahí, y quien busca "Charizard" quiere ver también el de Base Set.
    //
    // Devuelve null solo si TCGdex no responde en ninguno de los dos idiomas.
    public function buscarCartas(array $filtros, int $limite = 60): ?array
    {
        $idioma = Idiomas::activo();

        $propios = $this->consultarCartas($idioma, $filtros, $limite);

        $complemento = null;
        if ($idioma !== self::COMPLETO && ($propios === null || count($propios) < $limite)) {
            $complemento = $this->consultarCartas(self::COMPLETO, $filtros, $limite);
        }

        if ($propios === null && $complemento === null) {
            return null;
        }

        $cartas    = collect($propios ?? []);
        $conocidas = $cartas->pluck('id')->flip();

        foreach ($complemento ?? [] as $carta) {
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
    // Un solo catálogo, el que se pida. Que la lista de cartas venga vacía es
    // una respuesta legítima y no un error: en el catálogo español muchos sets
    // clásicos existen solo como metadatos (neo1 declara 111 cartas y no trae
    // ninguna). Quien llama decide qué hacer con eso; aquí no se disimula.
    //
    // Excepción: el logo y el símbolo sí se completan desde el inglés. Son
    // assets sin texto — el mismo dibujo vale en cualquier idioma — y el
    // catálogo español muchas veces no los trae aunque existan.
    public function obtenerSet(string $setId, string $idioma = 'es'): ?array
    {
        $set = $this->get($idioma, "sets/{$setId}");

        // null (no contestó) y [] (no lo tiene) salen tal cual: quien llama
        // necesita distinguirlos para saber si merece la pena reintentar
        if (empty($set)) {
            return $set;
        }

        if ($idioma !== self::COMPLETO && (empty($set['logo']) || empty($set['symbol']))) {
            $completo = $this->get(self::COMPLETO, "sets/{$setId}");

            foreach (['logo', 'symbol'] as $campo) {
                if (empty($set[$campo]) && !empty($completo[$campo])) {
                    $set[$campo] = $completo[$campo];
                }
            }
        }

        return $set;
    }

    // --- Detalle completo de una carta en un catálogo ---
    // GET /v2/{lang}/cards/{id}
    // Devuelve null si esa carta no existe en ese idioma, que es justo lo que
    // hay que saber para no volver a pedirla.
    public function obtenerCarta(string $cartaId, string $idioma = 'es'): ?array
    {
        return $this->get($idioma, "cards/{$cartaId}");
    }

    // --- Listado de todos los sets disponibles ---
    public function listarSets(string $idioma = 'es'): ?array
    {
        return $this->get($idioma, 'sets') ?? $this->get(self::COMPLETO, 'sets');
    }

    // ¿Merece la pena reintentar este fallo? Solo si es pasajero: una conexión
    // que se cae o un 5xx pueden ir bien al segundo intento. Un 404 no — pedir
    // tres veces algo que no existe es que te digan tres veces que no existe, y
    // con el catálogo español eso pasa constantemente (de los sets clásicos no
    // hay versión española).
    private function reintentable(\Throwable $e): bool
    {
        return !($e instanceof RequestException) || $e->response->serverError();
    }

    // Petición GET con reintentos, timeout y caché. Tres respuestas posibles, y
    // la diferencia entre las dos últimas es la que sostiene el cache-aside por
    // idioma:
    //
    //   array  → el recurso, tal cual
    //   []     → ese catálogo NO tiene este recurso (404), y no lo va a tener.
    //            Es una respuesta, no un fallo: se cachea, y así dejamos de
    //            preguntar por los sets clásicos que en español no existen.
    //   null   → TCGdex no contestó (5xx, timeout). Eso sí es un fallo, y no se
    //            cachea: el siguiente intento vuelve a preguntar.
    //
    // (Cache::remember no distingue un null guardado de una clave ausente, así
    // que devolver null es, de hecho, no cachear.)
    private function get(string $idioma, string $ruta, int $ttl = self::CACHE_TTL): ?array
    {
        return Cache::remember(
            "tcgdex:{$idioma}:{$ruta}",
            $ttl,
            function () use ($idioma, $ruta) {
                try {
                    $res = Http::retry(3, 300, $this->reintentable(...), throw: false)
                        ->timeout(15)
                        ->get(self::BASE_URL . "/{$idioma}/{$ruta}");

                    if ($res->successful()) {
                        return $res->json();
                    }

                    return $res->status() === 404 ? [] : null;
                } catch (\Throwable) {
                    return null;
                }
            }
        );
    }
}
