<?php

namespace App\Support;

use Illuminate\Support\Str;

// --- Taxonomía CERRADA de rarezas: diez categorías, con orden y símbolo ---
//
// TCGdex distingue 42 rarezas (y las que invente mañana). Para quien
// colecciona son diez: las del TCG real, cada una con el símbolo que va
// impreso en la carta, de menor a mayor rareza, y un cajón único
// ("excepciones") para promos, sets conmemorativos, Pocket y cualquier
// cosa que no lleve uno de los nueve símbolos.
//
// La BD sigue guardando la clave FINA de TCGdex en cartas.rareza_key
// (ver CatalogoTcg): es el valor crudo, sin pérdida, y la búsqueda en vivo
// lo necesita tal cual. La categoría se DERIVA aquí. Remapear es editar
// MAPA; ninguna carta que tocar.
//
// Este fichero es la única fuente de verdad de la taxonomía: clave, orden,
// símbolo y mapeo. Los nombres visibles están en lang/{es,en}/tcg.php
// ('categorias'), como el resto de textos de la app.
class Rarezas
{
    public const EXCEPCIONES = 'excepciones';

    // Orden canónico: el del desplegable y el de cualquier lista
    public const ORDEN = [
        'comun',
        'infrecuente',
        'rara',
        'doble_rara',
        'ace_spec',
        'ilustracion',
        'ultra_rara',
        'ilustracion_especial',
        'hiper_rara',
        self::EXCEPCIONES,
    ];

    // El símbolo impreso en la carta. El color es un NOMBRE, no un pixel:
    // el frontend lo resuelve a un token de CSS en cada tema.
    public const SIMBOLOS = [
        'comun'                => ['forma' => 'circulo',  'color' => 'negro',    'cantidad' => 1],
        'infrecuente'          => ['forma' => 'rombo',    'color' => 'negro',    'cantidad' => 1],
        'rara'                 => ['forma' => 'estrella', 'color' => 'negro',    'cantidad' => 1],
        'doble_rara'           => ['forma' => 'estrella', 'color' => 'negro',    'cantidad' => 2],
        'ace_spec'             => ['forma' => 'estrella', 'color' => 'rosa',     'cantidad' => 1],
        'ilustracion'          => ['forma' => 'estrella', 'color' => 'dorado',   'cantidad' => 1],
        'ultra_rara'           => ['forma' => 'estrella', 'color' => 'plateado', 'cantidad' => 2],
        'ilustracion_especial' => ['forma' => 'estrella', 'color' => 'dorado',   'cantidad' => 2],
        'hiper_rara'           => ['forma' => 'estrella', 'color' => 'dorado',   'cantidad' => 3],
        self::EXCEPCIONES      => null,
    ];

    // rareza_key de TCGdex → categoría. Criterio: el símbolo impreso.
    //
    // Las V/VMAX/VSTAR de Espada y Escudo llevan una estrella negra, así
    // que son "rara" aunque por nivel equivalgan a las ex de hoy. Las
    // Shiny usan el símbolo de la era Escarlata y Púrpura (Paldean Fates):
    // una y dos estrellas doradas. Radiant y Amazing tienen símbolo propio,
    // que no es ninguno de los nueve: excepciones.
    //
    // Lo que NO esté aquí (Pocket, 'none', 'promo', o una clave que TCGdex
    // se invente mañana) es excepciones por defecto. Ver categoria().
    private const MAPA = [
        'common'                    => 'comun',
        'uncommon'                  => 'infrecuente',
        'rare'                      => 'rara',
        'holo-rare'                 => 'rara',
        'rare-holo'                 => 'rara',
        'rare-holo-lvx'             => 'rara',
        'rare-prime'                => 'rara',
        'legend'                    => 'rara',
        'holo-rare-v'               => 'rara',
        'holo-rare-vmax'            => 'rara',
        'holo-rare-vstar'           => 'rara',
        'double-rare'               => 'doble_rara',
        'ace-spec-rare'             => 'ace_spec',
        'illustration-rare'         => 'ilustracion',
        'shiny-rare'                => 'ilustracion',
        'ultra-rare'                => 'ultra_rara',
        'full-art-trainer'          => 'ultra_rara',
        'shiny-rare-v'              => 'ultra_rara',
        'shiny-rare-vmax'           => 'ultra_rara',
        'special-illustration-rare' => 'ilustracion_especial',
        'shiny-ultra-rare'          => 'ilustracion_especial',
        'hyper-rare'                => 'hiper_rara',
        'mega-hyper-rare'           => 'hiper_rara',
        'secret-rare'               => 'hiper_rara',
        'black-white-rare'          => 'hiper_rara',
    ];

    // --- rareza_key → categoría ---
    // null (carta sin hidratar) sigue siendo null: no sabemos su rareza y
    // no la inventamos. Cualquier otra cosa que no esté en el mapa es
    // excepciones: la app no puede romperse ni crear una categoría fantasma
    // porque TCGdex estrene una rareza.
    public static function categoria(?string $rarezaKey): ?string
    {
        if ($rarezaKey === null || $rarezaKey === '') {
            return null;
        }

        return self::MAPA[$rarezaKey] ?? self::EXCEPCIONES;
    }

    // --- categoría → las rareza_key que caen en ella ---
    // Para el WHERE IN del catálogo y para la búsqueda en vivo. Solo las
    // claves que CatalogoTcg conoce: una clave desconocida guardada en BD
    // es excepciones por definición pero no se puede enumerar de antemano.
    public static function clavesTcgdex(string $categoria): array
    {
        $claves = [];
        foreach (array_keys(CatalogoTcg::RAREZAS) as $clave) {
            if (self::categoria($clave) === $categoria) {
                $claves[] = $clave;
            }
        }

        return $claves;
    }

    // Las rareza_key que tienen categoría propia (todas menos excepciones).
    // Su complemento, sobre las cartas hidratadas, son las excepciones.
    public static function clavesClasificadas(): array
    {
        return array_keys(self::MAPA);
    }

    // --- Texto crudo de TCGdex → rareza_key para guardar ---
    // Lo conocido se normaliza por CatalogoTcg (cualquier idioma). Lo
    // desconocido se guarda como slug ('Futuristic Rare' → 'futuristic-rare')
    // en vez de perderse: conserva el valor para remapear más adelante y
    // categoria() lo deja en excepciones mientras tanto.
    public static function claveDesdeTcgdex(?string $texto): ?string
    {
        $limpio = trim((string) $texto);
        if ($limpio === '') {
            return null;
        }

        return CatalogoTcg::claveRareza($limpio) ?? Str::slug($limpio);
    }

    // --- Lo que llega por la URL (?rareza=) → categoría ---
    // Acepta la categoría, una rareza_key antigua o el texto de TCGdex en
    // cualquiera de los dos idiomas: los enlaces de antes del cambio
    // (?rareza=holo-rare, ?rareza=Común) siguen llevando a algún sitio.
    public static function normalizar(?string $valor): ?string
    {
        $limpio = trim((string) $valor);
        if ($limpio === '') {
            return null;
        }
        if (in_array($limpio, self::ORDEN, true)) {
            return $limpio;
        }

        return self::categoria(CatalogoTcg::claveRareza($limpio));
    }

    public static function nombre(string $categoria): string
    {
        return __("tcg.categorias.{$categoria}");
    }

    // --- Lista para el desplegable de filtros ---
    // Recibe las rareza_key que hay de verdad en el catálogo y devuelve
    // solo las categorías con alguna, en orden canónico, ya traducidas.
    // [['clave' => 'comun', 'etiqueta' => 'Común', 'simbolo' => [...]], ...]
    public static function filtros(iterable $clavesPresentes): array
    {
        $presentes = [];
        foreach ($clavesPresentes as $clave) {
            $categoria = self::categoria($clave);
            if ($categoria !== null) {
                $presentes[$categoria] = true;
            }
        }

        $lista = [];
        foreach (self::ORDEN as $categoria) {
            if (isset($presentes[$categoria])) {
                $lista[] = [
                    'clave'    => $categoria,
                    'etiqueta' => self::nombre($categoria),
                    'simbolo'  => self::SIMBOLOS[$categoria],
                ];
            }
        }

        return $lista;
    }
}
