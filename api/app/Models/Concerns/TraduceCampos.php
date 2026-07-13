<?php

namespace App\Models\Concerns;

use App\Support\Idiomas;

// --- Campos guardados en una columna por idioma ---
// nombre_es / nombre_en, descripcion_es / descripcion_en, imagen_es / imagen_en.
//
// El modelo sigue exponiendo el campo con su nombre de siempre (carta.nombre),
// ya traducido: el frontend no se entera de que por debajo hay dos columnas, y
// no hubo que tocar ni una línea de la vista. Mismo truco que con tipo y rareza.
trait TraduceCampos
{
    // El campo en el idioma activo; si ahí no hay nada, en el primer idioma que
    // lo tenga. Devuelve null solo si no existe en ninguno.
    protected function traducido(string $campo): ?string
    {
        foreach (Idiomas::conRespaldo() as $idioma) {
            $valor = $this->getAttributeFromArray("{$campo}_{$idioma}");

            if ($valor !== null && $valor !== '') {
                return $valor;
            }
        }

        return null;
    }

    // Escribe en la columna del idioma activo.
    //
    // Es lo que mantiene en pie a Carta::create(['nombre' => 'Mewtwo']): una
    // carta que un admin da de alta a mano tiene un nombre, el del idioma en el
    // que la escribió. El sync no pasa por aquí — él sí sabe de qué catálogo
    // viene cada texto y escribe la columna concreta (nombre_es, nombre_en).
    protected function traducir(string $campo, $valor): void
    {
        $this->attributes["{$campo}_" . Idiomas::activo()] = $valor;
    }
}
