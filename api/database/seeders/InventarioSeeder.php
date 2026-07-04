<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventario; // Modelo que representa la tabla inventario

class InventarioSeeder extends Seeder
{
    public function run(): void
    {
        // Array con todos los datos de inventario a insertar
        // Cada entrada representa una carta en el inventario de un usuario
        // user_id  → id del usuario propietario
        // carta_id → id de la carta (referencia a la tabla cartas)
        // cantidad → cuántas copias tiene el usuario de esa carta
        // Los IDs corresponden a las primeras cartas del catálogo TCGdex
        // (set "151"), que CartasSeeder siembra justo antes que este seeder
        $datos = [
            // --- Inventario de Admin (user_id: 1) ---
            ['user_id' => 1, 'carta_id' => 1, 'cantidad' => 1], // 1x Bulbasaur
            ['user_id' => 1, 'carta_id' => 2, 'cantidad' => 2], // 2x Ivysaur
            ['user_id' => 1, 'carta_id' => 4, 'cantidad' => 1], // 1x Charmander

            // --- Inventario de Teo (user_id: 2) ---
            ['user_id' => 2, 'carta_id' => 3, 'cantidad' => 1], // 1x Venusaur ex
            ['user_id' => 2, 'carta_id' => 6, 'cantidad' => 2], // 2x Charizard ex
            ['user_id' => 2, 'carta_id' => 9, 'cantidad' => 1], // 1x Blastoise ex

            // --- Inventario de María (user_id: 3) ---
            ['user_id' => 3, 'carta_id' => 7,  'cantidad' => 3], // 3x Squirtle
            ['user_id' => 3, 'carta_id' => 10, 'cantidad' => 1], // 1x Caterpie
            ['user_id' => 3, 'carta_id' => 15, 'cantidad' => 2], // 2x Beedrill
        ];

        // Recorremos cada entrada del array
        foreach ($datos as $dato) {

            // updateOrCreate evita duplicados:
            // - Si ya existe esa combinación user_id + carta_id → actualiza la cantidad
            // - Si no existe → la crea nueva
            // Esto es importante porque el seeder puede ejecutarse varias veces
            Inventario::updateOrCreate(
                // Condición para buscar si ya existe
                ['user_id' => $dato['user_id'], 'carta_id' => $dato['carta_id']],
                // Datos a actualizar o insertar
                ['cantidad' => $dato['cantidad']]
            );
        }
    }
}