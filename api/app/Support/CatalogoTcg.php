<?php

namespace App\Support;

// --- Tipos y rarezas del TCG: conjunto CERRADO, con clave canónica ---
//
// El problema que resuelve esta clase:
//
// TCGdex devuelve el tipo y la rareza como TEXTO YA TRADUCIDO, distinto en
// cada catálogo ("Fire"/"Fuego"). Guardar ese texto en la BD hacía que la
// columna dependiese del idioma en que se cacheó cada carta, y como el sync
// tiene un fallback es→en para los sets sin traducir, acabaron conviviendo
// "Común" y "Common", "Rara" y "Rare", "Fuego" y "Fire" EN LA MISMA COLUMNA.
// Consecuencia: el filtro ?rareza=Común no encontraba las cartas guardadas
// como "Common". Era un bug real, en producción.
//
// A partir de aquí la BD guarda una CLAVE neutra ('fire', 'holo-rare') y el
// texto que ve el usuario sale del diccionario (lang/{es,en}/tcg.php). Un
// idioma nuevo se añade creando su lang/{idioma}/tcg.php: ni una carta que
// tocar, ni un re-sync.
//
// Aquí NO hay traducciones: hay PROTOCOLO. Los valores de abajo son los que
// TCGdex devuelve y acepta en cada catálogo, y sirven para dos cosas:
//   - normalizar lo que llega (cualquiera de los dos idiomas → clave)
//   - construir las consultas que le mandamos a TCGdex (clave → su idioma)
// Por eso el español de este fichero puede diferir del de lang/: ahí ponemos
// lo que queremos ENSEÑAR, aquí lo que TCGdex entiende.
class CatalogoTcg
{
    // 11 tipos. Correspondencia limpia 1:1 entre los dos catálogos.
    public const TIPOS = [
        'grass'     => ['en' => 'Grass',     'es' => 'Planta'],
        'fire'      => ['en' => 'Fire',      'es' => 'Fuego'],
        'water'     => ['en' => 'Water',     'es' => 'Agua'],
        'lightning' => ['en' => 'Lightning', 'es' => 'Rayo'],
        'psychic'   => ['en' => 'Psychic',   'es' => 'Psíquico'],
        'fighting'  => ['en' => 'Fighting',  'es' => 'Lucha'],
        'darkness'  => ['en' => 'Darkness',  'es' => 'Oscura'],
        'metal'     => ['en' => 'Metal',     'es' => 'Metálica'],
        'fairy'     => ['en' => 'Fairy',     'es' => 'Hada'],
        'dragon'    => ['en' => 'Dragon',    'es' => 'Dragón'],
        'colorless' => ['en' => 'Colorless', 'es' => 'Incolora'],
    ];

    // 40 rarezas. Aquí la correspondencia NO es 1:1: el catálogo español de
    // TCGdex tiene 35 y el inglés 40.
    //
    //   'es' => null  → la rareza solo existe en el catálogo inglés (sets
    //                   clásicos que nunca se tradujeron). Es el origen de
    //                   la mitad de la corrupción: el fallback es→en metía
    //                   el nombre inglés en la columna "española".
    //
    //   'es' => (en inglés)  → TCGdex NO la traduce en su propio catálogo
    //                   español ("Uncommon", "Shiny rare"...). Es el origen
    //                   de la otra mitad: 92 de nuestras cartas tienen la
    //                   rareza "Uncommon" y ninguna venía de un fallback.
    //                   En lang/es sí las traducimos: es una mejora sobre
    //                   los datos de TCGdex, no una copia.
    //
    // Ojo con 'holo-rare' y 'rare-holo': parecen la misma y no lo son.
    // "Holo Rare" son los sets modernos (swsh*, con español); "Rare Holo" los
    // clásicos (pl*, gym*, sin español). Verificado contra la API. Fundirlas
    // rompería las consultas a TCGdex, que las distingue.
    public const RAREZAS = [
        'none'                      => ['en' => 'None',                      'es' => 'Ninguno'],
        'common'                    => ['en' => 'Common',                    'es' => 'Común'],
        'uncommon'                  => ['en' => 'Uncommon',                  'es' => 'Uncommon'],
        'rare'                      => ['en' => 'Rare',                      'es' => 'Rara'],
        'holo-rare'                 => ['en' => 'Holo Rare',                 'es' => 'Holo Rara'],
        'holo-rare-v'               => ['en' => 'Holo Rare V',               'es' => 'Holo Rara V'],
        'holo-rare-vmax'            => ['en' => 'Holo Rare VMAX',            'es' => 'Holo Rara VMAX'],
        'holo-rare-vstar'           => ['en' => 'Holo Rare VSTAR',           'es' => 'Holo Rara VSTAR'],
        'rare-holo'                 => ['en' => 'Rare Holo',                 'es' => null],
        'rare-holo-lvx'             => ['en' => 'Rare Holo LV.X',            'es' => null],
        'rare-prime'                => ['en' => 'Rare PRIME',                'es' => null],
        'legend'                    => ['en' => 'LEGEND',                    'es' => null],
        'classic-collection'        => ['en' => 'Classic Collection',        'es' => null],
        'double-rare'               => ['en' => 'Double rare',               'es' => 'Rara Doble'],
        'ultra-rare'                => ['en' => 'Ultra Rare',                'es' => 'Ultra Rara'],
        'secret-rare'               => ['en' => 'Secret Rare',               'es' => 'Rara Secreta'],
        'hyper-rare'                => ['en' => 'Hyper rare',                'es' => 'Rara Híper'],
        'mega-hyper-rare'           => ['en' => 'Mega Hyper Rare',           'es' => 'Mega Hiper Rara'],
        'illustration-rare'         => ['en' => 'Illustration rare',         'es' => 'Rara Ilustración'],
        'special-illustration-rare' => ['en' => 'Special illustration rare', 'es' => 'Rara Ilustración Especial'],
        'radiant-rare'              => ['en' => 'Radiant Rare',              'es' => 'Rara Radiante'],
        'amazing-rare'              => ['en' => 'Amazing Rare',              'es' => 'Increíbles'],
        'ace-spec-rare'             => ['en' => 'ACE SPEC Rare',             'es' => 'Rara AS TÁCTICO'],
        'black-white-rare'          => ['en' => 'Black White Rare',          'es' => 'Rara Blanca y Negra'],
        'full-art-trainer'          => ['en' => 'Full Art Trainer',          'es' => 'Entrenador de arte completo'],
        'shiny-rare'                => ['en' => 'Shiny rare',                'es' => 'Shiny rare'],
        'shiny-rare-v'              => ['en' => 'Shiny rare V',              'es' => 'Shiny rare V'],
        'shiny-rare-vmax'           => ['en' => 'Shiny rare VMAX',           'es' => 'Shiny rare VMAX'],
        'shiny-ultra-rare'          => ['en' => 'Shiny Ultra Rare',          'es' => 'Rara Ultra Variocolor'],
        'promo'                     => ['en' => 'Promo',                     'es' => 'Promo'],
        // Rarezas de Pokémon Pocket. Sus series están excluidas por config,
        // pero TCGdex las sigue devolviendo en las búsquedas globales.
        'one-diamond'               => ['en' => 'One Diamond',               'es' => 'Un Diamante'],
        'two-diamond'               => ['en' => 'Two Diamond',               'es' => 'Dos Diamantes'],
        'three-diamond'             => ['en' => 'Three Diamond',             'es' => 'Tres Diamantes'],
        'four-diamond'              => ['en' => 'Four Diamond',              'es' => 'Cuatro Diamantes'],
        'one-star'                  => ['en' => 'One Star',                  'es' => 'Una Estrella'],
        'two-star'                  => ['en' => 'Two Star',                  'es' => 'Dos Estrellas'],
        'three-star'                => ['en' => 'Three Star',                'es' => 'Tres Estrellas'],
        'one-shiny'                 => ['en' => 'One Shiny',                 'es' => 'One Shiny'],
        'two-shiny'                 => ['en' => 'Two Shiny',                 'es' => 'Two Shiny'],
        'crown'                     => ['en' => 'Crown',                     'es' => 'Corona'],
    ];

    // Índices inversos (texto de TCGdex → clave), construidos una sola vez
    private static ?array $indiceTipos   = null;
    private static ?array $indiceRarezas = null;

    // --- Normalización: lo que llega de TCGdex → clave canónica ---
    // Acepta el valor en cualquiera de los dos catálogos, porque en la BD hay
    // de los dos. Insensible a mayúsculas y espacios: TCGdex no es del todo
    // consistente ("Double rare", "Holo Rare V").
    //
    // Si el valor no está en la tabla devuelve null en vez de inventarse una
    // clave: preferimos una rareza vacía a una rareza falsa que además
    // ensuciaría el desplegable de filtros.

    public static function claveTipo(?string $valor): ?string
    {
        return self::buscarClave($valor, self::TIPOS, self::indice('tipos'));
    }

    public static function claveRareza(?string $valor): ?string
    {
        return self::buscarClave($valor, self::RAREZAS, self::indice('rarezas'));
    }

    // --- Sentido contrario: clave → el texto que TCGdex entiende ---
    // Para las consultas que le mandamos (filtrar las cartas de un set por
    // tipo o rareza). Devuelve null si esa rareza no existe en ese catálogo,
    // y entonces el llamador sabe que no tiene sentido preguntar.

    public static function tipoTcgdex(?string $clave, string $idioma): ?string
    {
        return self::TIPOS[$clave][$idioma] ?? null;
    }

    public static function rarezaTcgdex(?string $clave, string $idioma): ?string
    {
        return self::RAREZAS[$clave][$idioma] ?? null;
    }

    // --- Listas para los desplegables de filtros, ya traducidas ---
    // [['clave' => 'fire', 'etiqueta' => 'Fuego'], ...]
    // La clave es lo que viaja en la URL (?tipo=fire): neutra al idioma, así
    // que un enlace filtrado se puede compartir entre usuarios de idiomas
    // distintos y cada uno lo ve en el suyo.

    public static function tipos(): array
    {
        return self::listar(array_keys(self::TIPOS), 'tcg.tipos');
    }

    public static function rarezas(): array
    {
        return self::listar(array_keys(self::RAREZAS), 'tcg.rarezas');
    }

    // --- Interno ---

    private static function listar(array $claves, string $prefijo): array
    {
        $lista = array_map(fn ($clave) => [
            'clave'    => $clave,
            'etiqueta' => __("{$prefijo}.{$clave}"),
        ], $claves);

        // Alfabético por la ETIQUETA, no por la clave: el desplegable lo lee
        // una persona, y en español "Común" va antes que "Holo Rara" aunque
        // sus claves sean 'common' y 'holo-rare'. Como el orden depende del
        // idioma activo, la lista se ordena aquí y no en la constante.
        usort($lista, fn ($a, $b) => strcmp(
            self::paraOrdenar($a['etiqueta']),
            self::paraOrdenar($b['etiqueta'])
        ));

        return $lista;
    }

    // Clave de ordenación alfabética. Se pliegan los acentos a mano en vez de
    // usar Collator (intl) porque esa extensión no está garantizada en todos
    // los entornos —en el nuestro no está—, y sin ella "Ámbar" acabaría
    // detrás de "Zafiro" por su byte. La "ñ" va después de la "n", como en el
    // alfabeto español: '~' es mayor que cualquier letra en ASCII.
    private static function paraOrdenar(string $texto): string
    {
        return strtr(mb_strtolower($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'n~', 'ç' => 'c',
        ]);
    }

    private static function indice(string $cual): array
    {
        $cache = $cual === 'tipos' ? self::$indiceTipos : self::$indiceRarezas;

        if ($cache === null) {
            $cache = [];
            $tabla = $cual === 'tipos' ? self::TIPOS : self::RAREZAS;

            foreach ($tabla as $clave => $idiomas) {
                foreach ($idiomas as $valor) {
                    if ($valor !== null) {
                        $cache[self::normalizar($valor)] = $clave;
                    }
                }
            }

            if ($cual === 'tipos') {
                self::$indiceTipos = $cache;
            } else {
                self::$indiceRarezas = $cache;
            }
        }

        return $cache;
    }

    private static function buscarClave(?string $valor, array $tabla, array $indice): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        // Ya es una clave canónica de ESTA tabla (viene de nuestra BD o de un
        // ?tipo=fire de la URL): se devuelve tal cual. Se comprueba contra la
        // tabla concreta y no contra las dos, para que una clave de rareza no
        // cuele como tipo.
        $limpio = trim($valor);
        if (isset($tabla[$limpio])) {
            return $limpio;
        }

        return $indice[self::normalizar($valor)] ?? null;
    }

    private static function normalizar(string $valor): string
    {
        return mb_strtolower(trim($valor));
    }
}
