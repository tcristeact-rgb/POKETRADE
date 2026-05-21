<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Carta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // Para validar los datos recibidos

class InventarioController extends Controller
{
    // --- Ver inventario del usuario autenticado ---
    // Endpoint: GET /api/inventario
    // Acceso: protegido (requiere token JWT)
    // Devuelve todas las cartas del usuario con sus datos completos
    public function index()
    {
        // Obtenemos el inventario del usuario autenticado
        // with('carta') carga los datos completos de cada carta (eager loading)
        // Evita el problema N+1: en lugar de hacer una consulta por carta,
        // hace una sola consulta adicional para traer todas las cartas
        $inventario = Inventario::with('carta')
            ->where('user_id', auth()->id()) // Solo las cartas del usuario autenticado
            ->get();

        return response()->json($inventario);
    }

    // --- Añadir carta al inventario ---
    // Endpoint: POST /api/inventario
    // Acceso: protegido (requiere token JWT)
    // Si la carta ya existe en el inventario, incrementa la cantidad
    // Si no existe, la crea con la cantidad indicada
    //
    // El frontend puede enviar de dos formas la carta a añadir:
    //   a) carta_id    → ID de una carta ya existente en el catálogo
    //   b) carta {...} → datos completos de un Pokémon de la PokeAPI; si ese
    //      Pokémon aún no está en el catálogo se crea en el momento.
    // El catálogo del frontend tiene >1000 Pokémon (PokeAPI) y la tabla
    // cartas solo guarda los que alguien posee o intercambia, creándose
    // bajo demanda. Sin esto solo podrían añadirse las 15 cartas del seeder.
    public function store(Request $request)
    {
        $cartaId = $request->input('carta_id');

        // Forma (b): llega un Pokémon de la PokeAPI sin carta_id.
        // Lo buscamos por numero (nº de Pokédex, único por Pokémon) y,
        // si no existe en el catálogo, lo creamos con los datos recibidos.
        if (!$cartaId && $request->filled('carta')) {
            $datos = $request->input('carta');

            $validacionCarta = Validator::make($datos, [
                'nombre' => 'required|string',
                'numero' => 'required|string',
            ]);
            if ($validacionCarta->fails()) {
                return response()->json(['error' => $validacionCarta->errors()->first()], 400);
            }

            $carta = Carta::firstOrCreate(
                ['numero' => $datos['numero']],
                [
                    'nombre'        => $datos['nombre'],
                    'tipo'          => $datos['tipo']          ?? null,
                    'rareza'        => $datos['rareza']        ?? null,
                    'set_expansion' => $datos['set_expansion'] ?? null,
                    'imagen_url'    => $datos['imagen_url']    ?? null,
                    'descripcion'   => $datos['descripcion']   ?? null,
                ]
            );
            $cartaId = $carta->id;
        }

        // Validamos la carta resultante y la cantidad
        $validacion = Validator::make(
            ['carta_id' => $cartaId, 'cantidad' => $request->cantidad],
            [
                // carta_id obligatorio y debe existir en la tabla cartas
                'carta_id' => 'required|exists:cartas,id',
                // cantidad opcional, si se envía debe ser un entero mayor que 0
                'cantidad' => 'nullable|integer|min:1',
            ]
        );

        // Si la validación falla devolvemos el primer error con código 400
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        // Comprobamos si el usuario ya tiene esa carta en su inventario
        // La restricción unique(['user_id', 'carta_id']) de la BD lo impide,
        // por eso primero buscamos si ya existe
        $entrada = Inventario::where('user_id', auth()->id())
            ->where('carta_id', $cartaId)
            ->first();

        if ($entrada) {
            // Si la carta ya existe en el inventario, incrementamos la cantidad
            // ?? 1 significa: si no se envía cantidad, incrementamos en 1
            $entrada->increment('cantidad', $request->cantidad ?? 1);
            return response()->json($entrada);
        }

        // Si la carta no existe en el inventario, la creamos como nueva entrada
        // ?? 1 significa: si no se envía cantidad, se guarda con cantidad 1
        $entrada = Inventario::create([
            'user_id'  => auth()->id(),  // ID del usuario autenticado
            'carta_id' => $cartaId,      // ID de la carta a añadir
            'cantidad' => $request->cantidad ?? 1,
        ]);

        // Devolvemos 201 (creado) con los datos de la nueva entrada
        return response()->json($entrada, 201);
    }

    // --- Eliminar carta del inventario ---
    // Endpoint: DELETE /api/inventario/{id}
    // Acceso: protegido (requiere token JWT)
    // Solo puede eliminar cartas de su propio inventario
    public function destroy($id)
    {
        // Buscamos la entrada por ID asegurándonos de que pertenece
        // al usuario autenticado — así un usuario no puede borrar
        // cartas del inventario de otro usuario
        $entrada = Inventario::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        // Si no se encuentra devolvemos 404
        if (!$entrada) {
            return response()->json(['error' => 'Carta no encontrada en tu inventario'], 404);
        }

        // Eliminamos la entrada del inventario
        $entrada->delete();

        return response()->json(['mensaje' => 'Carta eliminada del inventario']);
    }
}