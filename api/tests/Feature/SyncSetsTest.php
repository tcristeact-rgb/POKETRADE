<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Serie;
use App\Models\Set;

class SyncSetsTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    // Fakea el catálogo TCGdex: rutas concretas + 404 para el resto
    // (el orden importa: el comodín va el último)
    private function fakeCatalogo(array $rutas): void
    {
        Http::fake($rutas + ['api.tcgdex.net/*' => Http::response(null, 404)]);
    }

    // Resumen y detalle mínimos de un set para las respuestas simuladas
    private function setBrief(string $id, string $nombre): array
    {
        return ['id' => $id, 'name' => $nombre, 'cardCount' => ['total' => 1]];
    }

    private function setDetalle(string $id, string $nombre, array $extra = []): array
    {
        return $extra + [
            'id'        => $id,
            'name'      => $nombre,
            'cardCount' => ['total' => 1],
            'cards'     => [['id' => "{$id}-001", 'localId' => '001', 'name' => 'Carta']],
        ];
    }

    // --- Test 1: Las series excluidas por config no se importan ---
    // Ni en la pasada española ni en el complemento inglés
    public function test_sync_omite_las_series_excluidas_por_config()
    {
        config(['tcgdex.series_excluidas' => ['tcgp', 'mc']]);

        $this->fakeCatalogo([
            'api.tcgdex.net/v2/es/series' => Http::response([
                ['id' => 'sv', 'name' => 'Escarlata y Púrpura'],
                ['id' => 'tcgp', 'name' => 'Pocket'],
            ]),
            'api.tcgdex.net/v2/es/series/sv' => Http::response([
                'id' => 'sv', 'name' => 'Escarlata y Púrpura',
                'logo' => 'https://assets.tcgdex.net/es/sv/sv01/logo',
                'sets' => [$this->setBrief('sv01', 'SV Base')],
            ]),
            'api.tcgdex.net/v2/es/sets/sv01' => Http::response($this->setDetalle('sv01', 'SV Base', [
                'releaseDate' => '2023-03-31',
                'logo'        => 'https://assets.tcgdex.net/es/sv/sv01/logo',
                'symbol'      => 'https://assets.tcgdex.net/univ/sv/sv01/symbol',
            ])),
            // El complemento inglés solo trae series excluidas
            'api.tcgdex.net/v2/en/series' => Http::response([
                ['id' => 'mc', 'name' => "McDonald's Collection"],
            ]),
        ]);

        $this->artisan('tcgdex:sync-sets')->assertExitCode(0);

        $this->assertDatabaseHas('series', ['tcgdex_id' => 'sv']);
        $this->assertDatabaseHas('sets', ['tcgdex_id' => 'sv01']);
        $this->assertDatabaseMissing('series', ['tcgdex_id' => 'tcgp']);
        $this->assertDatabaseMissing('series', ['tcgdex_id' => 'mc']);
    }

    // --- Test 2: Logo de serie con fallback es → en ---
    // El detalle español no trae logo (caso real de sm, xy, bw...)
    // pero el inglés sí: debe guardarse el inglés
    public function test_logo_de_serie_cae_al_catalogo_ingles()
    {
        config(['tcgdex.series_excluidas' => []]);

        $this->fakeCatalogo([
            'api.tcgdex.net/v2/es/series'    => Http::response([['id' => 'sm', 'name' => 'Sol y Luna']]),
            'api.tcgdex.net/v2/es/series/sm' => Http::response([
                'id' => 'sm', 'name' => 'Sol y Luna', // sin logo, como en la API real
                'sets' => [$this->setBrief('sm1', 'Sol y Luna Base')],
            ]),
            'api.tcgdex.net/v2/en/series/sm' => Http::response([
                'id' => 'sm', 'name' => 'Sun & Moon',
                'logo' => 'https://assets.tcgdex.net/en/sm/sm1/logo',
            ]),
            'api.tcgdex.net/v2/es/sets/sm1' => Http::response($this->setDetalle('sm1', 'Sol y Luna Base', [
                'logo' => 'https://assets.tcgdex.net/es/sm/sm1/logo',
            ])),
            'api.tcgdex.net/v2/en/series' => Http::response([]),
        ]);

        $this->artisan('tcgdex:sync-sets')->assertExitCode(0);

        $this->assertSame(
            'https://assets.tcgdex.net/en/sm/sm1/logo',
            Serie::firstWhere('tcgdex_id', 'sm')->logo_url
        );
    }

    // --- Test 3: Logo de serie heredado del set más reciente ---
    // Sin logo de serie en ningún idioma: hereda el del set con logo
    // de fecha de lanzamiento más nueva
    public function test_logo_de_serie_hereda_del_set_mas_reciente()
    {
        config(['tcgdex.series_excluidas' => []]);

        $this->fakeCatalogo([
            'api.tcgdex.net/v2/es/series'    => Http::response([['id' => 'neo', 'name' => 'Neo']]),
            'api.tcgdex.net/v2/es/series/neo' => Http::response([
                'id' => 'neo', 'name' => 'Neo', // sin logo
                'sets' => [$this->setBrief('neo1', 'Neo Genesis'), $this->setBrief('neo2', 'Neo Discovery')],
            ]),
            'api.tcgdex.net/v2/en/series/neo' => Http::response([
                'id' => 'neo', 'name' => 'Neo', // tampoco en inglés
            ]),
            'api.tcgdex.net/v2/es/sets/neo1' => Http::response($this->setDetalle('neo1', 'Neo Genesis', [
                'releaseDate' => '2000-12-16',
                'logo'        => 'https://assets.tcgdex.net/en/neo/neo1/logo',
                'symbol'      => 'https://assets.tcgdex.net/univ/neo/neo1/symbol',
            ])),
            'api.tcgdex.net/v2/es/sets/neo2' => Http::response($this->setDetalle('neo2', 'Neo Discovery', [
                'releaseDate' => '2001-06-01',
                'logo'        => 'https://assets.tcgdex.net/en/neo/neo2/logo',
                'symbol'      => 'https://assets.tcgdex.net/univ/neo/neo2/symbol',
            ])),
            'api.tcgdex.net/v2/en/series' => Http::response([]),
        ]);

        $this->artisan('tcgdex:sync-sets')->assertExitCode(0);

        // Hereda el de neo2 (2001), que es más reciente que neo1 (2000)
        $this->assertSame(
            'https://assets.tcgdex.net/en/neo/neo2/logo',
            Serie::firstWhere('tcgdex_id', 'neo')->logo_url
        );
    }

    // --- Test 4: Logo y símbolo de set con fallback por campo es → en ---
    // El detalle español del set está completo pero sin logo/símbolo;
    // el inglés los tiene: deben rellenarse los huecos
    public function test_logo_de_set_se_completa_desde_el_ingles()
    {
        config(['tcgdex.series_excluidas' => []]);

        $this->fakeCatalogo([
            'api.tcgdex.net/v2/es/series'    => Http::response([['id' => 'xy', 'name' => 'XY']]),
            'api.tcgdex.net/v2/es/series/xy' => Http::response([
                'id' => 'xy', 'name' => 'XY', 'logo' => 'https://assets.tcgdex.net/es/xy/xy0/logo',
                'sets' => [$this->setBrief('xy1', 'XY Base')],
            ]),
            // Detalle es completo (1 de 1 cartas) pero sin logo ni símbolo
            'api.tcgdex.net/v2/es/sets/xy1' => Http::response($this->setDetalle('xy1', 'XY Base', [
                'releaseDate' => '2014-02-05',
            ])),
            'api.tcgdex.net/v2/en/sets/xy1' => Http::response($this->setDetalle('xy1', 'XY', [
                'logo'   => 'https://assets.tcgdex.net/en/xy/xy1/logo',
                'symbol' => 'https://assets.tcgdex.net/univ/xy/xy1/symbol',
            ])),
            'api.tcgdex.net/v2/en/series' => Http::response([]),
        ]);

        $this->artisan('tcgdex:sync-sets')->assertExitCode(0);

        $set = Set::firstWhere('tcgdex_id', 'xy1');
        $this->assertSame('https://assets.tcgdex.net/en/xy/xy1/logo', $set->logo_url);
        $this->assertSame('https://assets.tcgdex.net/univ/xy/xy1/symbol', $set->simbolo_url);
    }
}
