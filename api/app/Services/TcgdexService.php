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

    // Campos que se completan desde "en" si "es" los trae vacíos.
    // Solo los tres primeros disparan la petición de fallback: los
    // demás pueden faltar legítimamente (Entrenador/Energía sin tipos,
    // cartas sin descripción) y no justifican una petición extra.
    private const CAMPOS_FALLBACK  = ['name', 'rarity', 'image', 'illustrator', 'description'];
    private const CAMPOS_CRITICOS  = ['name', 'rarity', 'image'];

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
    public function obtenerSet(string $setId): ?array
    {
        return $this->get('es', "sets/{$setId}") ?? $this->get('en', "sets/{$setId}");
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
    private function get(string $idioma, string $ruta): ?array
    {
        return Cache::remember(
            "tcgdex:{$idioma}:{$ruta}",
            self::CACHE_TTL,
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
