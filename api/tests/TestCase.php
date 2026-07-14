<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // --- Los tests NO tocan una base de datos que no sea desechable ---
    //
    // RefreshDatabase VACÍA la base de datos que se encuentre. phpunit.xml la
    // apunta a :memory: precisamente para que eso sea inofensivo... pero si hay
    // una configuración cacheada (bootstrap/cache/config.php), Laravel IGNORA las
    // variables de phpunit.xml y usa la cacheada. Los tests corren entonces contra
    // la base de datos de DESARROLLO y se la llevan por delante.
    //
    // No es hipotético: pasó. Se cachó la config para medir el arranque, se lanzó
    // `php artisan test` a pelo, y la suite borró las 2.265 cartas locales. Por eso
    // `composer test` hace un `config:clear` antes — pero eso solo protege a quien
    // usa ese atajo, y `php artisan test` es más corto de escribir.
    //
    // Va en refreshApplication() y no en setUp() porque tiene que ejecutarse ANTES
    // que setUpTraits(), que es donde RefreshDatabase hace su trabajo. Para cuando
    // setUp() corre, ya sería tarde.
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $conexion = config('database.default');
        $destino  = config("database.connections.{$conexion}.database");

        if ($conexion === 'sqlite' && $destino === ':memory:') {
            return;
        }

        throw new RuntimeException(
            "Los tests iban a correr contra «{$destino}» ({$conexion}) en vez de :memory:, y "
            . "RefreshDatabase vacía la base de datos que encuentre. Se paran aquí.\n\n"
            . "  La causa casi siempre es la misma: hay una configuración cacheada, y con ella "
            . "Laravel ignora las variables de phpunit.xml — incluida DB_DATABASE=:memory:.\n\n"
            . "  Arréglalo con:  php artisan config:clear\n"
            . "  (o lanza la suite con `composer test`, que ya lo hace por ti)"
        );
    }

    // Idioma explícito en todas las peticiones de los tests.
    //
    // No es cosmético: Symfony\Request::create() —que es lo que usa por debajo
    // el cliente HTTP de los tests— inyecta 'HTTP_ACCEPT_LANGUAGE' => 'en-us,
    // en;q=0.5' por defecto. Con el middleware EstablecerIdioma en marcha, esa
    // cabecera fantasma hace que TODA la suite pida las respuestas en inglés y
    // que cualquier assert sobre un mensaje en español falle sin motivo
    // aparente.
    //
    // Fijándolo aquí, los tests comprueban el idioma por defecto de la API, que
    // es el que se lleva un cliente que no pide nada. La negociación en sí se
    // prueba aparte, en IdiomaTest, mandando la cabecera a propósito.
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'es');
    }
}
