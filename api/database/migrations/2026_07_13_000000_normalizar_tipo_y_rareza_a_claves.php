<?php

use App\Support\CatalogoTcg;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// --- tipo/rareza: de texto traducido a clave canónica ---
//
// Las columnas tipo y rareza guardaban el TEXTO que devolvía TCGdex, que es
// distinto en cada idioma. Como el sync tiene fallback es→en y el propio
// catálogo español de TCGdex deja rarezas sin traducir, la columna acabó
// mezclando los dos idiomas: conviven "Común" y "Common", "Rara" y "Rare",
// "Fuego" y "Fire". El filtro ?rareza=Común no encontraba las cartas
// guardadas como "Common": un bug real, ya en producción.
//
// A partir de aquí se guarda una clave neutra ('fire', 'holo-rare') y el
// texto sale del diccionario (lang/{es,en}/tcg.php). El backfill acepta los
// valores en cualquiera de los dos idiomas, que es justo lo que hay.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartas', function (Blueprint $table) {
            // Indexadas: son las columnas por las que filtra el catálogo
            $table->string('tipo_key')->nullable()->index()->after('nombre');
            $table->string('rareza_key')->nullable()->index()->after('tipo_key');
        });

        $this->rellenarClaves();

        // Fuera las columnas viejas: dejarlas sería conservar un dato corrupto
        // y ambiguo, y además chocarían con los accesores tipo/rareza del
        // modelo, que ahora sirven el texto traducido.
        Schema::table('cartas', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'rareza']);
        });
    }

    // Traduce lo que haya en las columnas viejas a claves canónicas. En
    // bloques para no cargar el catálogo entero en memoria (hoy son ~2.000
    // filas, pero crece con cada set que alguien visita).
    private function rellenarClaves(): void
    {
        DB::table('cartas')
            ->select('id', 'tipo', 'rareza')
            ->orderBy('id')
            ->chunk(500, function ($cartas) {
                foreach ($cartas as $carta) {
                    // Un valor que no esté en la tabla se queda a null: mejor
                    // sin rareza que con una rareza inventada, que además
                    // ensuciaría el desplegable de filtros.
                    DB::table('cartas')->where('id', $carta->id)->update([
                        'tipo_key'   => CatalogoTcg::claveTipo($carta->tipo),
                        'rareza_key' => CatalogoTcg::claveRareza($carta->rareza),
                    ]);
                }
            });
    }

    // La vuelta atrás repuebla las columnas con el texto ESPAÑOL de TCGdex,
    // que es el idioma en el que estaba la mayor parte de los datos. No
    // reconstruye la mezcla original de idiomas, claro: eso era el bug.
    public function down(): void
    {
        Schema::table('cartas', function (Blueprint $table) {
            $table->string('tipo')->nullable();
            $table->string('rareza')->nullable();
        });

        DB::table('cartas')
            ->select('id', 'tipo_key', 'rareza_key')
            ->orderBy('id')
            ->chunk(500, function ($cartas) {
                foreach ($cartas as $carta) {
                    DB::table('cartas')->where('id', $carta->id)->update([
                        'tipo'   => CatalogoTcg::tipoTcgdex($carta->tipo_key, 'es'),
                        'rareza' => CatalogoTcg::rarezaTcgdex($carta->rareza_key, 'es'),
                    ]);
                }
            });

        Schema::table('cartas', function (Blueprint $table) {
            $table->dropIndex(['tipo_key']);
            $table->dropIndex(['rareza_key']);
            $table->dropColumn(['tipo_key', 'rareza_key']);
        });
    }
};
