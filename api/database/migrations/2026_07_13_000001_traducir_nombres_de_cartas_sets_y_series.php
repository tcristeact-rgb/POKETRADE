<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// --- Los datos de carta, por idioma ---
//
// Tipos y rarezas son un conjunto CERRADO: se resolvieron con una clave
// canónica y un diccionario (migración anterior). Los nombres y las
// descripciones no: son texto libre, distinto por carta, y TCGdex los sirve
// ya traducidos en cada catálogo. Aquí no hay diccionario que valga — hay que
// guardarlos.
//
// Se guardan en columnas por idioma (nombre_es / nombre_en) y no en una tabla
// de traducciones aparte: el grid del catálogo es la consulta más caliente de
// la aplicación y una tabla aparte le añadiría un JOIN a cambio de nada.
//
// La IMAGEN también es texto traducido, aunque no lo parezca: el asset lleva
// el idioma en la ruta (assets.tcgdex.net/{idioma}/...) porque la ilustración
// incluye el texto impreso de la carta. Y no se puede componer el idioma a
// mano: /es/neo/neo1/1 devuelve 404 — de los sets clásicos solo existe el
// asset inglés. Por eso la imagen también va por columnas, y un NULL significa
// exactamente lo que parece: "en este idioma no existe".
//
// De qué catálogo salió cada fila lo dice su propia imagen: el sync es es→en y
// TCGdex solo devuelve el asset en el idioma en el que realmente tiene la
// carta. 1624 filas con imagen /es/ y 601 con /en/ (los 5 sets clásicos:
// base1, neo1, gym2, ecard3, col1).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartas', function (Blueprint $table) {
            $table->string('nombre_es')->nullable()->after('tcgdex_id');
            $table->string('nombre_en')->nullable()->after('nombre_es');
            $table->text('descripcion_es')->nullable();
            $table->text('descripcion_en')->nullable();
            $table->text('imagen_es')->nullable();
            $table->text('imagen_en')->nullable();

            // De qué catálogos ya hemos pedido el detalle de esta carta.
            // No basta con "¿está vacío nombre_es?": de un set clásico NUNCA
            // va a llegar, y sin esta marca lo reintentaríamos en cada visita.
            // Guarda el intento, no el resultado.
            $table->json('idiomas_detallados')->nullable();
        });

        $this->repartirCartasPorIdioma();

        Schema::table('cartas', function (Blueprint $table) {
            // set_expansion era una copia denormalizada del nombre del set, en
            // español. Se va: el nombre traducido sale de la relación con sets
            // (167 filas, precargadas), y la columna solo podía quedarse obsoleta.
            $table->dropColumn(['nombre', 'descripcion', 'imagen_url', 'set_expansion']);
        });

        // --- sets y series ---
        // Solo 185 filas entre las dos, así que el nombre traducido lo rellena
        // el propio índice: tcgdex:sync-sets ya recorre los dos catálogos.
        // Aquí se copia el nombre actual a las dos columnas para que nada quede
        // en blanco entre la migración y el primer sync, que las reescribe.
        foreach (['series', 'sets'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('nombre_es')->nullable()->after('tcgdex_id');
                $table->string('nombre_en')->nullable()->after('nombre_es');
            });

            DB::table($tabla)->update([
                'nombre_es' => DB::raw('nombre'),
                'nombre_en' => DB::raw('nombre'),
            ]);

            Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('nombre'));
        }

        Schema::table('sets', function (Blueprint $table) {
            // Qué catálogos ya hemos volcado a la tabla cartas. synced_at sigue
            // diciendo "este set ya está cacheado"; esto dice "y en qué idiomas",
            // que es lo que decide si una visita en inglés dispara una petición.
            $table->json('idiomas_sincronizados')->nullable()->after('synced_at');
        });

        $this->marcarIdiomasCacheadosDeCadaSet();
    }

    // Cada fila va a la columna del catálogo del que salió, y el idioma lo
    // delata la ruta del asset. Las 40 cartas sin imagen (ecard3, mee) caen en
    // español, que es por donde empieza siempre el sync.
    private function repartirCartasPorIdioma(): void
    {
        DB::table('cartas')->orderBy('id')->chunkById(500, function ($cartas) {
            foreach ($cartas as $carta) {
                $idioma = $this->idiomaDelAsset($carta->imagen_url);

                DB::table('cartas')->where('id', $carta->id)->update([
                    "nombre_{$idioma}"      => $carta->nombre,
                    "descripcion_{$idioma}" => $carta->descripcion,
                    "imagen_{$idioma}"      => $carta->imagen_url,
                    'idiomas_detallados'    => $carta->detalle_synced_at ? json_encode([$idioma]) : null,
                ]);
            }
        });
    }

    // Un set está cacheado en un idioma si alguna de sus cartas tiene nombre en
    // ese idioma. Es la marca exacta: sale de lo que de verdad hay en la BD, no
    // de una suposición sobre qué catálogo respondió aquel día.
    private function marcarIdiomasCacheadosDeCadaSet(): void
    {
        foreach (DB::table('sets')->whereNotNull('synced_at')->get() as $set) {
            $idiomas = [];

            foreach (['es', 'en'] as $idioma) {
                $tiene = DB::table('cartas')
                    ->where('set_id', $set->tcgdex_id)
                    ->whereNotNull("nombre_{$idioma}")
                    ->exists();

                if ($tiene) {
                    $idiomas[] = $idioma;
                }
            }

            DB::table('sets')->where('id', $set->id)
                ->update(['idiomas_sincronizados' => json_encode($idiomas)]);
        }
    }

    private function idiomaDelAsset(?string $imagenUrl): string
    {
        preg_match('#^https://assets\.tcgdex\.net/([a-z]{2})/#', (string) $imagenUrl, $coincidencia);

        return ($coincidencia[1] ?? 'es') === 'en' ? 'en' : 'es';
    }

    // Vuelta atrás: se recupera el idioma que había antes (español preferido,
    // inglés donde no hay español) y el nombre del set se vuelve a denormalizar.
    public function down(): void
    {
        Schema::table('cartas', function (Blueprint $table) {
            $table->string('nombre')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('imagen_url')->nullable();
            $table->string('set_expansion')->nullable();
        });

        foreach (['series', 'sets'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->string('nombre')->nullable());
            DB::table($tabla)->update(['nombre' => DB::raw('COALESCE(nombre_es, nombre_en)')]);
            Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn(['nombre_es', 'nombre_en']));
        }

        DB::table('cartas')->update([
            'nombre'      => DB::raw('COALESCE(nombre_es, nombre_en)'),
            'descripcion' => DB::raw('COALESCE(descripcion_es, descripcion_en)'),
            'imagen_url'  => DB::raw('COALESCE(imagen_es, imagen_en)'),
        ]);

        DB::statement('UPDATE cartas SET set_expansion = (SELECT nombre FROM sets WHERE sets.tcgdex_id = cartas.set_id)');

        Schema::table('cartas', fn (Blueprint $t) => $t->dropColumn([
            'nombre_es', 'nombre_en', 'descripcion_es', 'descripcion_en',
            'imagen_es', 'imagen_en', 'idiomas_detallados',
        ]));

        Schema::table('sets', fn (Blueprint $t) => $t->dropColumn('idiomas_sincronizados'));
    }
};
