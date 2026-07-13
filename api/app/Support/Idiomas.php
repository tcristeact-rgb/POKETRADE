<?php

namespace App\Support;

// --- Los idiomas de PokeTrade ---
// Una sola lista, y todo el backend la mira: el middleware que negocia
// Accept-Language, los modelos que eligen la columna traducida y el sync que
// recorre los catálogos de TCGdex. Añadir un idioma es añadirlo aquí y crear
// su carpeta lang/{codigo}/.
//
// El primero es el de respaldo por defecto (config('app.locale')).
final class Idiomas
{
    public const SOPORTADOS = ['es', 'en'];

    public static function activo(): string
    {
        return app()->getLocale();
    }

    public static function soportado(?string $idioma): bool
    {
        return in_array($idioma, self::SOPORTADOS, true);
    }

    // El idioma activo primero y el resto detrás: es el orden en el que se
    // busca un texto traducido. Un hueco (una carta que todavía no se ha
    // hidratado en inglés, un set clásico que no existe en español) se rellena
    // con lo que haya en otro idioma antes que dejar un vacío en pantalla.
    public static function conRespaldo(): array
    {
        return array_values(array_unique([self::activo(), ...self::SOPORTADOS]));
    }
}
