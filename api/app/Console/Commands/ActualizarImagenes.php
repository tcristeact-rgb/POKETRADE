<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Carta;
use Illuminate\Support\Facades\Http; // Cliente HTTP de Laravel para hacer peticiones externas

class ActualizarImagenes extends Command
{
    // Nombre del comando para ejecutarlo desde la terminal
    // Uso: php artisan cartas:actualizar-imagenes
    protected $signature = 'cartas:actualizar-imagenes';

    // Descripción que aparece al hacer php artisan list
    protected $description = 'Actualiza las imágenes de las cartas desde la PokeAPI';

    public function handle()
    {
        // Obtenemos solo las cartas que no tienen imagen_url asignada
        // Así evitamos hacer peticiones innecesarias a la API para cartas que ya tienen imagen
        $cartas = Carta::whereNull('imagen_url')->get();

        foreach ($cartas as $carta) {
            // Hacemos una petición GET a la PokeAPI usando el número de la carta
            // withoutVerifying() desactiva la verificación SSL (útil en entornos locales)
            // El número de pokédex (ej: "006") identifica al Pokémon en la API
            $res = Http::withoutVerifying()->get("https://pokeapi.co/api/v2/pokemon/{$carta->numero}");

            if ($res->successful()) {
                // Extraemos la URL del artwork oficial del JSON de respuesta
                // La PokeAPI devuelve varios tipos de imágenes, usamos el artwork oficial
                $url = $res->json()['sprites']['other']['official-artwork']['front_default'];

                // Actualizamos la carta en la BD con la URL obtenida
                $carta->update(['imagen_url' => $url]);

                // Mensaje de éxito en la consola (en verde)
                $this->info("Actualizada: {$carta->nombre}");
            } else {
                // Si la petición falla, mostramos el error en consola (en rojo)
                // y continuamos con la siguiente carta
                $this->error("Error en: {$carta->nombre}");
            }
        }

        // Mensaje final cuando el proceso ha terminado
        $this->info('Proceso completado');
    }
}