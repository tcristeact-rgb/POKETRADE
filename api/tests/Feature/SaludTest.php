<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// GET /api/health — el endpoint más barato de la API.
//
// No usa RefreshDatabase a propósito: la gracia de esta ruta es justamente que la
// base de datos le da igual.
class SaludTest extends TestCase
{
    public function test_responde_que_el_servicio_esta_en_pie(): void
    {
        $this->getJson('/api/health')
             ->assertStatus(200)
             ->assertJsonPath('ok', true)
             ->assertJsonStructure(['ok', 'hora']);
    }

    // Lo que hace útil a este endpoint es lo que NO hace. Si algún día alguien le
    // añade una comprobación de la base de datos "ya que estamos", dejaría de
    // servir para lo que se hizo: despertar a Render por el mínimo coste, y
    // distinguir "la API está caída" de "Supabase está caída", que no es lo mismo.
    public function test_no_toca_la_base_de_datos_ni_llama_a_nadie(): void
    {
        Http::fake();   // cualquier petición saliente haría fallar el test

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/health')->assertStatus(200);

        $this->assertCount(0, DB::getQueryLog(), 'GET /api/health ha consultado la base de datos.');
        Http::assertNothingSent();
    }
}
