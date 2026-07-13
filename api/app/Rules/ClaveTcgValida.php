<?php

namespace App\Rules;

use App\Support\CatalogoTcg;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// El tipo y la rareza son un conjunto CERRADO: o el valor está en el catálogo
// o no existe. Sin esta regla, el endpoint de admin dejaría escribir una
// rareza inventada, que luego no tendría traducción y saldría en pantalla como
// la clave en crudo.
//
// Acepta la clave canónica ('fire') o el nombre en cualquiera de los idiomas
// que TCGdex maneja ('Fuego', 'Fire'): es el mismo criterio que usa el resto
// de la app para normalizar lo que entra.
class ClaveTcgValida implements ValidationRule
{
    public function __construct(private string $tipo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clave = $this->tipo === 'tipo'
            ? CatalogoTcg::claveTipo((string) $value)
            : CatalogoTcg::claveRareza((string) $value);

        if ($clave === null) {
            $fail('validation.custom.tcg.desconocido')->translate([
                'attribute' => $attribute,
            ]);
        }
    }
}
