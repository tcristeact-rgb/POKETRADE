<?php

namespace Tests\Feature;

use App\Models\Carta;
use App\Support\CatalogoTcg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Tipos y rarezas: clave canónica en la BD, texto traducido en pantalla.
//
// El test central es el primero: la columna rareza guardaba el texto que
// devolvía TCGdex, distinto en cada idioma, así que acabaron conviviendo
// "Común" y "Common" para la MISMA rareza. El filtro ?rareza=Común no
// encontraba las cartas guardadas como "Common". Estaba en producción.
class CatalogoTcgTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_solo_filtro_encuentra_las_cartas_que_antes_se_partian_en_dos(): void
    {
        // Así estaba la BD: la misma rareza escrita en dos idiomas, según el
        // idioma en el que se cacheó cada carta
        Carta::create(['nombre' => 'Bulbasaur', 'rareza_key' => CatalogoTcg::claveRareza('Común')]);
        Carta::create(['nombre' => 'Clefable',  'rareza_key' => CatalogoTcg::claveRareza('Common')]);

        // Las dos acaban en la misma clave
        $this->assertSame('common', Carta::where('nombre', 'Bulbasaur')->value('rareza_key'));
        $this->assertSame('common', Carta::where('nombre', 'Clefable')->value('rareza_key'));

        // Y un solo filtro las encuentra a las dos. Antes devolvía una.
        $this->getJson('/api/cartas?rareza=common')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_el_mismo_enlace_filtrado_vale_en_los_dos_idiomas(): void
    {
        Carta::create(['nombre' => 'Charizard', 'tipo_key' => 'fire']);
        Carta::create(['nombre' => 'Blastoise', 'tipo_key' => 'water']);

        // La clave no depende del idioma: el enlace se puede compartir entre
        // un usuario español y uno inglés, y cada uno lo ve en el suyo
        foreach (['es' => 'Fuego', 'en' => 'Fire'] as $idioma => $etiqueta) {
            $this->withHeader('Accept-Language', $idioma)
                ->getJson('/api/cartas?tipo=fire')
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.nombre', 'Charizard')
                ->assertJsonPath('data.0.tipo', $etiqueta)      // traducido
                ->assertJsonPath('data.0.tipo_key', 'fire');    // la clave, siempre igual
        }
    }

    public function test_los_enlaces_antiguos_con_el_nombre_siguen_funcionando(): void
    {
        Carta::create(['nombre' => 'Charizard', 'tipo_key' => 'fire']);

        // Un marcador de antes de esta fase llevaba ?tipo=Fuego
        $this->getJson('/api/cartas?tipo=Fuego')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Charizard');

        // Y uno guardado cuando la carta se cacheó en inglés, ?tipo=Fire
        $this->getJson('/api/cartas?tipo=Fire')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Charizard');
    }

    public function test_normaliza_desde_los_dos_catalogos_de_tcgdex(): void
    {
        // Es lo que hace el sync: TCGdex devuelve el texto ya traducido, y el
        // fallback es→en de los sets sin traducir metía el inglés en la
        // columna "española"
        $this->assertSame('fire',  CatalogoTcg::claveTipo('Fuego'));
        $this->assertSame('fire',  CatalogoTcg::claveTipo('Fire'));
        $this->assertSame('fire',  CatalogoTcg::claveTipo('fire'));     // ya es clave
        $this->assertSame('rare',  CatalogoTcg::claveRareza('Rara'));
        $this->assertSame('rare',  CatalogoTcg::claveRareza('Rare'));

        // Insensible a mayúsculas y espacios: TCGdex no es del todo constante
        $this->assertSame('double-rare', CatalogoTcg::claveRareza('  double RARE '));
    }

    public function test_un_valor_desconocido_se_queda_a_null_y_no_se_inventa(): void
    {
        // "Eléctrico" es un tipo del videojuego: en el TCG no existe
        $this->assertNull(CatalogoTcg::claveTipo('Eléctrico'));
        $this->assertNull(CatalogoTcg::claveRareza('Rarísima'));
        $this->assertNull(CatalogoTcg::claveTipo(''));

        // Una clave de rareza no cuela como tipo
        $this->assertNull(CatalogoTcg::claveTipo('common'));
    }

    public function test_holo_rare_y_rare_holo_no_se_confunden(): void
    {
        // Parecen la misma y no lo son: "Holo Rare" son los sets modernos
        // (con español) y "Rare Holo" los clásicos (solo en inglés).
        // Fundirlas rompería las consultas a TCGdex, que las distingue.
        $this->assertSame('holo-rare', CatalogoTcg::claveRareza('Holo Rare'));
        $this->assertSame('holo-rare', CatalogoTcg::claveRareza('Holo Rara'));
        $this->assertSame('rare-holo', CatalogoTcg::claveRareza('Rare Holo'));

        // La clásica no existe en el catálogo español de TCGdex: por eso no
        // se le puede preguntar por ella en español
        $this->assertNull(CatalogoTcg::rarezaTcgdex('rare-holo', 'es'));
        $this->assertSame('Rare Holo', CatalogoTcg::rarezaTcgdex('rare-holo', 'en'));

        // Y aun así el usuario español tiene un nombre para ella
        $this->assertSame('Holo Rara (clásica)', __('tcg.rarezas.rare-holo'));
    }

    public function test_una_carta_sin_tipo_no_inventa_uno(): void
    {
        // Entrenador y Energía no tienen tipo: el JSON debe traer null, no
        // una clave a medio traducir
        Carta::create(['nombre' => 'Poké Ball']);

        $this->getJson('/api/cartas')
            ->assertJsonPath('data.0.tipo', null)
            ->assertJsonPath('data.0.rareza', null);
    }

    public function test_toda_clave_del_catalogo_tiene_traduccion_en_los_dos_idiomas(): void
    {
        // Sin esto, una rareza sin traducir saldría en pantalla como su clave
        // en crudo ("holo-rare-vstar"), que es peor que no salir
        foreach (['es', 'en'] as $idioma) {
            app()->setLocale($idioma);

            foreach (array_keys(CatalogoTcg::TIPOS) as $clave) {
                $this->assertNotSame("tcg.tipos.{$clave}", __("tcg.tipos.{$clave}"),
                    "Falta la traducción del tipo '{$clave}' en {$idioma}");
            }
            foreach (array_keys(CatalogoTcg::RAREZAS) as $clave) {
                $this->assertNotSame("tcg.rarezas.{$clave}", __("tcg.rarezas.{$clave}"),
                    "Falta la traducción de la rareza '{$clave}' en {$idioma}");
            }
        }
    }
}
