<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
