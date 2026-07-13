<?php

namespace Tests\Feature;

use App\Models\Carta;
use App\Models\Serie;
use App\Models\Set;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Los DATOS de carta —nombre, descripción, ilustración— en el idioma de quien
// mira. Aquí no hay diccionario que valga: es texto libre, distinto por carta,
// y TCGdex lo sirve ya traducido en cada catálogo. Se guarda en una columna por
// idioma y se rellena bajo demanda, un catálogo por visita.
class DatosPorIdiomaTest extends TestCase
{
    use RefreshDatabase;

    private function crearSet(array $atributos = []): Set
    {
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);

        return Set::create($atributos + [
            'tcgdex_id'     => 'sv03.5',
            'serie_id'      => $serie->id,
            'nombre_es'     => '151',
            'nombre_en'     => '151',
            'numero_cartas' => 1,
        ]);
    }

    // Respuesta de GET /v2/{lang}/sets/sv03.5 con una carta
    private function setTcgdex(string $idioma, string $nombre): array
    {
        return [
            'id'        => 'sv03.5',
            'name'      => '151',
            'cardCount' => ['total' => 1],
            'cards'     => [[
                'id'      => 'sv03.5-001',
                'localId' => '001',
                'name'    => $nombre,
                'image'   => "https://assets.tcgdex.net/{$idioma}/sv/sv03.5/001",
            ]],
        ];
    }

    // --- Cache-aside por idioma ---
    // Un set cacheado en español no está cacheado en inglés: la primera visita
    // inglesa se trae ESE catálogo, y solo ese. Una petición, no un backfill.
    public function test_la_primera_visita_en_ingles_trae_el_catalogo_ingles(): void
    {
        $set = $this->crearSet(['synced_at' => now(), 'idiomas_sincronizados' => ['es']]);
        Carta::create([
            'tcgdex_id' => 'sv03.5-001',
            'set_id'    => 'sv03.5',
            'nombre_es' => 'Ivysaur',
            'imagen_es' => 'https://assets.tcgdex.net/es/sv/sv03.5/001',
        ]);

        Http::fake([
            'api.tcgdex.net/v2/en/sets/sv03.5' => Http::response($this->setTcgdex('en', 'Ivysaur (EN)')),
        ]);

        $this->withHeader('Accept-Language', 'en')
             ->getJson('/api/sets/sv03.5/cartas')
             ->assertStatus(200)
             ->assertJsonPath('data.0.nombre', 'Ivysaur (EN)')
             ->assertJsonPath('data.0.imagen_low', 'https://assets.tcgdex.net/en/sv/sv03.5/001/low.webp');

        // El español sigue intacto: cada catálogo escribe en SUS columnas
        $this->assertDatabaseHas('cartas', [
            'tcgdex_id' => 'sv03.5-001',
            'nombre_es' => 'Ivysaur',
            'nombre_en' => 'Ivysaur (EN)',
            'imagen_es' => 'https://assets.tcgdex.net/es/sv/sv03.5/001',
            'imagen_en' => 'https://assets.tcgdex.net/en/sv/sv03.5/001',
        ]);

        $this->assertSame(['es', 'en'], $set->fresh()->idiomas_sincronizados);
    }

    // Y la segunda visita en inglés ya no pregunta nada: para eso se cacheó
    public function test_la_segunda_visita_en_ingles_no_llama_a_tcgdex(): void
    {
        $this->crearSet(['synced_at' => now(), 'idiomas_sincronizados' => ['es', 'en']]);
        Carta::create([
            'tcgdex_id' => 'sv03.5-001',
            'set_id'    => 'sv03.5',
            'nombre_es' => 'Ivysaur',
            'nombre_en' => 'Ivysaur (EN)',
        ]);

        Http::fake();

        $this->withHeader('Accept-Language', 'en')
             ->getJson('/api/sets/sv03.5/cartas')
             ->assertStatus(200)
             ->assertJsonPath('data.0.nombre', 'Ivysaur (EN)');

        Http::assertNothingSent();
    }

    // --- Un set que en español NO existe ---
    // De Neo Genesis no hay versión española y no la va a haber. Se pregunta
    // UNA vez, se anota el intento, y no se vuelve a preguntar nunca: la
    // diferencia entre guardar el intento y guardar el resultado.
    public function test_un_set_sin_version_espanola_se_pregunta_una_sola_vez(): void
    {
        $set = $this->crearSet(['synced_at' => now(), 'idiomas_sincronizados' => ['en']]);
        Carta::create([
            'tcgdex_id' => 'sv03.5-001',
            'set_id'    => 'sv03.5',
            'nombre_en' => 'Ampharos',
        ]);

        // 404: ese catálogo no tiene el set. Es una respuesta, no un fallo.
        Http::fake(['api.tcgdex.net/v2/es/sets/sv03.5' => Http::response(null, 404)]);

        // La carta se sirve igual, con el nombre que tiene: mejor el inglés
        // que un hueco
        $this->getJson('/api/sets/sv03.5/cartas')
             ->assertStatus(200)
             ->assertJsonPath('data.0.nombre', 'Ampharos');

        $this->assertSame(['en', 'es'], $set->fresh()->idiomas_sincronizados);
        Http::assertSentCount(1);

        // Segunda visita española: ni una petición más
        Http::fake();
        $this->getJson('/api/sets/sv03.5/cartas')->assertStatus(200);
        Http::assertNothingSent();
    }

    // Si TCGdex se cae, en cambio, NO se anota nada: eso sí hay que reintentarlo
    public function test_si_tcgdex_se_cae_el_intento_no_se_da_por_hecho(): void
    {
        $set = $this->crearSet(['synced_at' => now(), 'idiomas_sincronizados' => ['en']]);
        Carta::create(['tcgdex_id' => 'sv03.5-001', 'set_id' => 'sv03.5', 'nombre_en' => 'Ampharos']);

        Http::fake(['api.tcgdex.net/*' => Http::response(null, 500)]);

        $this->getJson('/api/sets/sv03.5/cartas')->assertStatus(200);

        // El español sigue sin intentarse: la próxima visita lo volverá a pedir
        $this->assertSame(['en'], $set->fresh()->idiomas_sincronizados);
    }

    // --- La ilustración también es texto traducido ---
    // El asset lleva el idioma en la ruta porque el dibujo incluye el texto
    // impreso de la carta. Y no se puede componer a mano: de los sets clásicos
    // solo existe el asset inglés, y /es/... devuelve 404. Por eso un NULL en
    // imagen_es significa "no existe", y se sirve el inglés.
    public function test_la_carta_sin_ilustracion_espanola_sirve_la_inglesa(): void
    {
        $this->crearSet(['synced_at' => now(), 'idiomas_sincronizados' => ['es', 'en']]);
        Carta::create([
            'tcgdex_id' => 'neo1-1',
            'set_id'    => 'sv03.5',
            'nombre_en' => 'Ampharos',
            'imagen_en' => 'https://assets.tcgdex.net/en/neo/neo1/1',
        ]);

        Http::fake();

        // Un español pide la carta y recibe la ilustración inglesa, que es la
        // única que hay. Sin el respaldo, aquí habría un 404 en el <img>.
        $this->getJson('/api/sets/sv03.5/cartas')
             ->assertJsonPath('data.0.imagen_low', 'https://assets.tcgdex.net/en/neo/neo1/1/low.webp');
    }

    // --- El nombre del set, traducido y sin copiarlo en cada carta ---
    public function test_el_nombre_del_set_llega_traducido_en_cada_carta(): void
    {
        $serie = Serie::create(['tcgdex_id' => 'swsh', 'nombre_es' => 'Espada y Escudo', 'nombre_en' => 'Sword & Shield']);
        Set::create([
            'tcgdex_id'             => 'swsh12',
            'serie_id'              => $serie->id,
            'nombre_es'             => 'Tempestad Plateada',
            'nombre_en'             => 'Silver Tempest',
            'synced_at'             => now(),
            'idiomas_sincronizados' => ['es', 'en'],
        ]);
        Carta::create(['tcgdex_id' => 'swsh12-1', 'set_id' => 'swsh12', 'nombre_es' => 'Bellsprout']);

        Http::fake();

        $this->withHeader('Accept-Language', 'es')->getJson('/api/cartas')
             ->assertJsonPath('data.0.set_expansion', 'Tempestad Plateada');

        $this->withHeader('Accept-Language', 'en')->getJson('/api/cartas')
             ->assertJsonPath('data.0.set_expansion', 'Silver Tempest');
    }

    // El set viaja precargado con la carta: si no, cada carta del grid sería una
    // consulta más solo para saber cómo se llama su expansión (N+1). 24 cartas
    // por página no pueden costar 25 consultas.
    public function test_el_grid_no_hace_una_consulta_por_carta(): void
    {
        $set = $this->crearSet();

        foreach (range(1, 20) as $i) {
            Carta::create(['tcgdex_id' => "sv03.5-{$i}", 'set_id' => 'sv03.5', 'nombre_es' => "Carta {$i}"]);
        }

        DB::enableQueryLog();
        $this->getJson('/api/cartas')->assertStatus(200)->assertJsonCount(20, 'data');
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // El paginador (count + select) y UNA carga de los sets. Ni una más.
        $this->assertLessThanOrEqual(4, $consultas,
            "El grid hizo {$consultas} consultas para 20 cartas: se ha colado un N+1.");
    }

    // --- Buscar por nombre, en todos los idiomas a la vez ---
    // Los nombres de Pokémon coinciden en los dos catálogos, pero los de
    // Entrenador no. Y una carta que aún no se ha hidratado en inglés solo
    // tiene nombre español: buscar solo en la columna activa la escondería.
    public function test_la_busqueda_por_nombre_encuentra_la_carta_en_cualquier_idioma(): void
    {
        Carta::create(['nombre_es' => 'Investigación del Profesor', 'nombre_en' => "Professor's Research"]);
        Carta::create(['nombre_es' => 'Cordón de Cartas']);   // aún sin hidratar en inglés

        // Un inglés busca por el nombre inglés...
        $this->withHeader('Accept-Language', 'en')->getJson('/api/cartas?nombre=Professor')
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.nombre', "Professor's Research");

        // ...y encuentra igualmente la que solo está en español, que es mejor
        // que no encontrar nada
        $this->withHeader('Accept-Language', 'en')->getJson('/api/cartas?nombre=Cordón')
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.nombre', 'Cordón de Cartas');
    }

    // --- Hidratación perezosa del detalle, también por idioma ---
    public function test_abrir_la_carta_en_ingles_hidrata_solo_el_detalle_ingles(): void
    {
        $carta = Carta::create([
            'tcgdex_id'      => 'sv03.5-025',
            'set_id'         => 'sv03.5',
            'nombre_es'      => 'Pikachu',
            'descripcion_es' => 'Cuando varios se juntan, su energía puede provocar tormentas.',
        ]);

        Http::fake(['api.tcgdex.net/v2/en/cards/sv03.5-025' => Http::response([
            'id'          => 'sv03.5-025',
            'name'        => 'Pikachu',
            'localId'     => '025',
            'description' => 'When several of these Pokémon gather, their electricity can build and cause lightning storms.',
            'types'       => ['Lightning'],
            'rarity'      => 'Common',
            'hp'          => 60,
            'image'       => 'https://assets.tcgdex.net/en/sv/sv03.5/025',
        ])]);

        $this->withHeader('Accept-Language', 'en')
             ->getJson("/api/cartas/{$carta->id}")
             ->assertStatus(200)
             ->assertJsonPath('descripcion', 'When several of these Pokémon gather, their electricity can build and cause lightning storms.')
             ->assertJsonPath('rareza', 'Common');   // el conjunto cerrado, por diccionario

        $carta->refresh();

        // La descripción española sigue donde estaba
        $this->assertSame('Cuando varios se juntan, su energía puede provocar tormentas.', $carta->descripcion_es);
        $this->assertSame(['en'], $carta->idiomas_detallados);

        // Y el español, que nunca se ha pedido, sigue pendiente de hidratar
        $this->assertFalse($carta->detalladoEn('es'));
    }

    // Una carta de un set clásico abierta en español: el catálogo español no la
    // tiene, así que el detalle lo trae el inglés — tipo, rareza, precio y hp no
    // dependen del idioma, y sus textos ingleses ya están pagados.
    public function test_una_carta_solo_inglesa_se_hidrata_desde_el_ingles(): void
    {
        $carta = Carta::create(['tcgdex_id' => 'neo1-1', 'set_id' => 'neo1', 'nombre_en' => 'Ampharos']);

        Http::fake([
            'api.tcgdex.net/v2/es/cards/neo1-1' => Http::response(null, 404),
            'api.tcgdex.net/v2/en/cards/neo1-1' => Http::response([
                'id'      => 'neo1-1',
                'name'    => 'Ampharos',
                'localId' => '1',
                'rarity'  => 'Rare Holo',
                'types'   => ['Lightning'],
                'hp'      => 100,
            ]),
        ]);

        $this->getJson("/api/cartas/{$carta->id}")
             ->assertStatus(200)
             ->assertJsonPath('nombre', 'Ampharos')
             ->assertJsonPath('hp', 100)
             // La rareza sí sale en español: es conjunto cerrado, y su nombre
             // no depende de que TCGdex tenga la carta traducida
             ->assertJsonPath('rareza', 'Holo Rara (clásica)');

        $carta->refresh();

        // Los dos catálogos quedan anotados: el español porque respondió que no
        // la tiene, el inglés porque sí. Ninguno se volverá a pedir.
        $this->assertEqualsCanonicalizing(['es', 'en'], $carta->idiomas_detallados);
        $this->assertNotNull($carta->detalle_synced_at);
    }
}
