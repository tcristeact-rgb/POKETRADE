<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Carta;
use Illuminate\Support\Facades\Http;

class ActualizarImagenes extends Command
{
    protected $signature = 'cartas:actualizar-imagenes';
    protected $description = 'Actualiza las imágenes de las cartas desde la PokeAPI';

    public function handle()
    {
        $cartas = Carta::whereNull('imagen_url')->get();
        
        foreach ($cartas as $carta) {
            $res = Http::withoutVerifying()->get("https://pokeapi.co/api/v2/pokemon/{$carta->numero}");
            
            if ($res->successful()) {
                $url = $res->json()['sprites']['other']['official-artwork']['front_default'];
                $carta->update(['imagen_url' => $url]);
                $this->info("Actualizada: {$carta->nombre}");
            } else {
                $this->error("Error en: {$carta->nombre}");
            }
        }

        $this->info('Proceso completado');
    }
}