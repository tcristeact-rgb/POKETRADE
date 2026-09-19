<?php

namespace App\Services;

use App\Models\Carta;
use App\Support\CatalogoTcg;

// El único sitio que sabe convertir lo que devuelve TCGdex en columnas de la
// tabla cartas. Antes vivía dos veces: en CartaController (detalle de una
// carta) y en SetController (resumen de las cartas de un set, para el upsert
// del cache-aside). Un campo que cambie en TCGdex se cambia aquí y ya.
//
// El idioma entra siempre por parámetro. Nada aquí lee el locale activo de la
// petición: así el hidratador se prueba sin fijar el de la aplicación, y quien
// llama dice explícitamente en qué catálogo está trabajando.
class HidratadorDeCartas
{
    public function __construct(private TcgdexService $tcgdex)
    {
    }

    // Crea la fila de una carta de TCGdex que aún no está en la BD
    // (abierta desde la búsqueda global). Nace ya hidratada: el detalle
    // completo viene en la misma petición que valida que existe.
    public function crear(string $tcgdexId, string $idioma): ?Carta
    {
        $datos = $this->tcgdex->obtenerCarta($tcgdexId, $idioma);

        // Que el catálogo del idioma activo no la tenga no significa que la
        // carta no exista: las de los sets clásicos solo están en inglés
        if (empty($datos) && $idioma !== TcgdexService::COMPLETO) {
            $idioma = TcgdexService::COMPLETO;
            $datos  = $this->tcgdex->obtenerCarta($tcgdexId, $idioma);
        }

        if (empty($datos['name'])) {
            return null;
        }

        // firstOrCreate por si dos usuarios abren la misma carta a la vez
        // (tcgdex_id tiene índice único)
        return Carta::firstOrCreate(
            ['tcgdex_id' => $datos['id'] ?? $tcgdexId],
            $this->camposNeutros($datos) + $this->columnasPorIdioma($datos, $idioma) + [
                // El id de TCGdex es "{set}-{numero}": si el detalle no
                // trae el set, se deriva del prefijo
                'set_id'             => $datos['set']['id'] ?? strtok($tcgdexId, '-'),
                'idiomas_detallados' => [$idioma],
            ]
        );
    }

    // Completa el detalle de la carta desde TCGdex (rareza, tipo, precio,
    // descripción...) en el idioma pedido. Si la API externa no responde, no
    // pasa nada: se sirve lo que haya en la BD y no se marca nada, así la
    // próxima visita vuelve a intentarlo.
    public function hidratar(Carta $carta, string $idioma): void
    {
        $catalogos = [$idioma => $this->tcgdex->obtenerCarta($carta->tcgdex_id, $idioma)];

        // Si el catálogo del idioma pedido no tiene la carta, se pide al inglés:
        // los campos neutros (tipo, rareza, hp, precio, ilustrador) los da
        // igual de bien cualquiera de los dos, y de paso nos quedamos sus
        // textos ingleses, que ya están pagados.
        if ($catalogos[$idioma] === [] && $idioma !== TcgdexService::COMPLETO) {
            $catalogos[TcgdexService::COMPLETO] = $this->tcgdex->obtenerCarta($carta->tcgdex_id, TcgdexService::COMPLETO);
        }

        foreach ($catalogos as $codigo => $datos) {
            // null = no contestó. Ni se guarda ni se marca: se reintentará.
            if ($datos === null) {
                continue;
            }

            // Cualquier otra cosa es una respuesta, aunque sea para decir que
            // ese catálogo no tiene la carta. El intento queda anotado y no se
            // repite: de un set clásico no va a salir una versión española por
            // mucho que la pidamos.
            $carta->idiomas_detallados = array_values(array_unique([
                ...($carta->idiomas_detallados ?? []),
                $codigo,
            ]));

            if ($datos === []) {
                continue;
            }

            $carta->fill($this->camposNeutros($datos, $carta) + $this->columnasPorIdioma($datos, $codigo, $carta));
        }

        $carta->save();
    }

    // La carta con su detalle en el idioma pedido, creándola si no está en
    // la BD e hidratándola si está pero sin detalle. Es lo mismo que hace
    // CartaController::show() al abrir una carta por su id de TCGdex.
    // Devuelve null si TCGdex no contestó: hidratar() solo deja el idioma
    // anotado cuando hubo respuesta, así que si tras hidratar sigue sin
    // anotar es que no la hubo.
    public function conDetalle(string $tcgdexId, string $idioma): ?Carta
    {
        $carta = Carta::firstWhere('tcgdex_id', $tcgdexId) ?? $this->crear($tcgdexId, $idioma);

        if ($carta && !$carta->detalladoEn($idioma)) {
            $this->hidratar($carta, $idioma);

            if (!$carta->detalladoEn($idioma)) {
                return null;
            }
        }

        return $carta;
    }

    // La fila que el cache-aside de un set mete en el upsert por tcgdex_id,
    // a partir del RESUMEN de una carta (lo que trae /sets/{id}: id, nombre,
    // imagen y número; sin descripción ni campos neutros). Todas las filas de
    // un upsert llevan las mismas claves, de ahí los ?? null explícitos.
    public function filaDeResumen(array $resumen, string $idioma, string $setId): array
    {
        $columnas = $this->columnasPorIdioma($resumen, $idioma);

        // El resumen no trae descripción, y el upsert no la toca
        unset($columnas["descripcion_{$idioma}"]);

        return ['tcgdex_id' => $resumen['id']] + $columnas + [
            'numero'     => $resumen['localId'] ?? null,
            'set_id'     => $setId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // Los campos que SÍ dependen del idioma: nombre, descripción e imagen (el
    // asset de TCGdex también va por catálogo). Con una carta existente, lo
    // que el payload no traiga conserva su valor actual.
    private function columnasPorIdioma(array $datos, string $idioma, ?Carta $carta = null): array
    {
        return [
            "nombre_{$idioma}"      => $datos['name'] ?? $carta?->{"nombre_{$idioma}"},
            "descripcion_{$idioma}" => $datos['description'] ?? $carta?->{"descripcion_{$idioma}"},
            "imagen_{$idioma}"      => $datos['image'] ?? $carta?->{"imagen_{$idioma}"},
        ];
    }

    // Los campos que NO dependen del idioma: los da igual de bien cualquier
    // catálogo de TCGdex. El tipo y la rareza llegan como texto ya traducido
    // ("Fire" / "Fuego") y se normalizan a la clave canónica, que es lo único
    // que guarda la BD.
    private function camposNeutros(array $datos, ?Carta $carta = null): array
    {
        return [
            'tipo_key'          => CatalogoTcg::claveTipo($datos['types'][0] ?? null) ?? $carta?->tipo_key,
            'rareza_key'        => CatalogoTcg::claveRareza($datos['rarity'] ?? null) ?? $carta?->rareza_key,
            'numero'            => $datos['localId'] ?? $carta?->numero,
            'ilustrador'        => $datos['illustrator'] ?? $carta?->ilustrador,
            'hp'                => $datos['hp'] ?? $carta?->hp,
            'precio_cardmarket' => $datos['pricing']['cardmarket']['avg']
                                    ?? $datos['pricing']['cardmarket']['trend']
                                    ?? $carta?->precio_cardmarket,
            'detalle_synced_at' => now(),
        ];
    }
}
