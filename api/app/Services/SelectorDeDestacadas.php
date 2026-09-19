<?php

namespace App\Services;

use App\Models\Carta;
use App\Models\Set;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

// Elige las cartas del hero del home. Es un algoritmo con estado propio
// (intentos, cartas ya conocidas) y sin nada de HTTP: el controlador solo
// pone la caché por idioma delante.
//
// Primero la BD (las recientes con precio, luego las más caras del catálogo
// entero); si no llega a cuatro, un paseo por TCGdex empezando por el set más
// reciente que ya tenga precios en Cardmarket; y si ni así, cartas sin precio
// pero con imagen. El idioma entra por parámetro: nada aquí lee el locale de
// la aplicación.
class SelectorDeDestacadas
{
    // Cuántas cartas enseña el hero del home
    private const DESTACADAS = 4;

    // Semanas que tarda Cardmarket en tener precio de un set recién salido.
    // Medido el 2026-09-19 contra TCGdex: los dos sets lanzados hacía 3 días,
    // 0 cartas con precio; desde el de hacía 9 semanas hacia atrás, todas. Ocho
    // deja margen. Equivocarse por arriba solo cuesta empezar por un set algo
    // más viejo; por abajo, gastar 12 llamadas a TCGdex para no encontrar nada
    // y dejar la portada sin hero — que es lo que pasaba.
    private const SEMANAS_HASTA_TENER_PRECIO = 8;

    public function __construct(
        private TcgdexService $tcgdex,
        private HidratadorDeCartas $hidratador,
    ) {
    }

    // La selección en sí: primero la BD, y solo si no llega a 4, TCGdex
    public function seleccionar(string $idioma): array
    {
        $ventana = Carta::orderByDesc('id')->limit(200)->pluck('id');

        // Recientes con precio, de mayor a menor
        $destacadas = Carta::whereIn('id', $ventana)
            ->whereNotNull('precio_cardmarket')
            ->orderByDesc('precio_cardmarket')
            ->limit(self::DESTACADAS)
            ->get();

        // Fallback: completar con las más caras del catálogo entero
        // (las recientes conservan la primera posición: son la
        // novedad que el hero quiere enseñar)
        if ($destacadas->count() < self::DESTACADAS) {
            $destacadas = $destacadas->concat(
                Carta::whereNotNull('precio_cardmarket')
                    ->whereNotIn('id', $destacadas->pluck('id'))
                    ->orderByDesc('precio_cardmarket')
                    ->limit(self::DESTACADAS - $destacadas->count())
                    ->get()
            );
        }

        // Último recurso: la BD no tiene bastantes cartas con precio. Las
        // traídas en vivo van detrás y, entre ellas, de mayor a menor precio
        if ($destacadas->count() < self::DESTACADAS) {
            $destacadas = $destacadas->concat(
                collect($this->completarDesdeTcgdex($destacadas, self::DESTACADAS - $destacadas->count(), $idioma))
                    ->sortByDesc('precio_cardmarket')
            );
        }

        // Último recurso: si ni la BD ni el paseo por TCGdex dan cuatro con
        // precio, se completa con cartas sin precio. El hero las enseña igual
        // (el precio es opcional en su etiqueta), pero sin imagen no hay nada
        // que enseñar, así que esas quedan fuera. Las más recientes primero:
        // suelen ser las que el propio paseo acaba de hidratar.
        if ($destacadas->count() < self::DESTACADAS) {
            $destacadas = $destacadas->concat(
                Carta::conImagen()
                    ->whereNull('precio_cardmarket')
                    ->whereNotIn('id', $destacadas->pluck('id'))
                    ->orderByDesc('id')
                    ->limit(self::DESTACADAS - $destacadas->count())
                    ->get()
            );
        }

        return $destacadas->values()->toArray();
    }

    // Trae de TCGdex las cartas que faltan para completar el hero.
    //
    // Se recorren los sets más recientes del índice y, dentro de cada uno,
    // sus cartas DESDE EL FINAL: ahí viven las secretas y las de ilustración
    // especial, que son las caras y las que un escaparate quiere enseñar.
    // Cada carta cuesta una petición de detalle (es la única forma de saber
    // su precio), así que se hidratan solo las necesarias, con un tope por
    // si el set no trae precios.
    //
    // Las cartas se PERSISTEN, por el mismo camino que abrirlas en el
    // detalle: el hero enlaza por id interno, y así la siguiente selección
    // ya las encuentra en la BD sin volver a preguntar. Es cache-aside, no
    // un sembrado: solo entran las cuatro que se van a enseñar.
    //
    // Si TCGdex no contesta (null) se corta en seco y se devuelve lo que
    // haya: insistir con más peticiones contra una API caída solo alarga
    // la espera.
    private function completarDesdeTcgdex(Collection $yaElegidas, int $faltan, string $idioma): array
    {
        $extra     = [];
        $conocidas = $yaElegidas->pluck('tcgdex_id')->filter()->flip()->all();
        $intentos  = $faltan * 3;

        foreach ($this->setsCandidatos() as $setId) {
            $set = $this->tcgdex->obtenerSet($setId, $idioma);

            // El catálogo del idioma activo puede no tener el set: el inglés
            // es el completo
            if ($set === [] && $idioma !== TcgdexService::COMPLETO) {
                $set = $this->tcgdex->obtenerSet($setId, TcgdexService::COMPLETO);
            }

            if ($set === null) {
                return $extra;
            }

            // Un set demasiado nuevo no tiene precios en Cardmarket: hidratar
            // sus cartas es gastar intentos para nada. Cubre el camino en vivo
            // (sin índice en la BD no hay fecha_lanzamiento que filtrar antes).
            if ($this->demasiadoNuevoParaTenerPrecio($set['releaseDate'] ?? null)) {
                continue;
            }

            foreach (array_reverse($set['cards'] ?? []) as $resumen) {
                if (count($extra) >= $faltan || $intentos <= 0) {
                    return $extra;
                }
                if (isset($conocidas[$resumen['id']])) {
                    continue;
                }

                $intentos--;
                $carta = $this->hidratador->conDetalle($resumen['id'], $idioma);

                if ($carta === null) {
                    return $extra;
                }

                $conocidas[$resumen['id']] = true;

                if ($carta->precio_cardmarket !== null) {
                    $extra[] = $carta;
                }
            }
        }

        return $extra;
    }

    // Los sets donde buscar cartas para el hero, del más reciente que ya
    // tenga precios en Cardmarket hacia atrás. Salen del índice que deja
    // tcgdex:sync-sets, que ya viene sin las series excluidas por config; los
    // lanzados hace menos de SEMANAS_HASTA_TENER_PRECIO y los que no tienen
    // fecha se descartan. Si el índice no da ningún candidato (BD recién
    // creada), se pide a TCGdex el último set de la última serie no excluida:
    // dos peticiones, cacheadas 24 h; ahí el filtro por fecha lo aplica
    // completarDesdeTcgdex con el releaseDate de cada set.
    private function setsCandidatos(): array
    {
        $sets = Set::whereNotNull('fecha_lanzamiento')
            ->where('fecha_lanzamiento', '<=', $this->fechaLimiteParaTenerPrecio())
            ->orderByDesc('fecha_lanzamiento')
            ->limit(3)
            ->pluck('tcgdex_id')
            ->all();

        if ($sets !== []) {
            return $sets;
        }

        $excluidas = array_flip(config('tcgdex.series_excluidas', []));
        $series    = array_values(array_filter(
            $this->tcgdex->listarSeries(TcgdexService::COMPLETO) ?? [],
            fn ($serie) => !isset($excluidas[$serie['id']])
        ));

        if ($series === []) {
            return [];
        }

        $detalle = $this->tcgdex->obtenerSerie(end($series)['id'], TcgdexService::COMPLETO);

        return array_reverse(array_column($detalle['sets'] ?? [], 'id'));
    }

    // Último día de lanzamiento con el que un set ya tiene precios (ver
    // SEMANAS_HASTA_TENER_PRECIO)
    private function fechaLimiteParaTenerPrecio(): string
    {
        return now()->subWeeks(self::SEMANAS_HASTA_TENER_PRECIO)->toDateString();
    }

    // Solo dice "sí" con una fecha válida y posterior al límite: sin fecha (o
    // con una que no parsea) no se descarta nada, que es lo que hacía siempre.
    private function demasiadoNuevoParaTenerPrecio(?string $releaseDate): bool
    {
        if (!$releaseDate) {
            return false;
        }

        try {
            return Carbon::parse($releaseDate)->toDateString() > $this->fechaLimiteParaTenerPrecio();
        } catch (\Throwable) {
            return false;
        }
    }
}
