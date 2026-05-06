<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Carta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventarioController extends Controller
{
    // Ver el inventario del usuario autenticado
    public function index()
    {
        $inventario = Inventario::with('carta')
            ->where('user_id', auth()->id())
            ->get();

        return response()->json($inventario);
    }

    // Añadir carta al inventario
    public function store(Request $request)
    {
        $validacion = Validator::make($request->all(), [
            'carta_id' => 'required|exists:cartas,id',
            'cantidad' => 'nullable|integer|min:1',
        ]);

        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        // Si ya existe esa carta en el inventario, aumenta la cantidad
        $entrada = Inventario::where('user_id', auth()->id())
            ->where('carta_id', $request->carta_id)
            ->first();

        if ($entrada) {
            $entrada->increment('cantidad', $request->cantidad ?? 1);
            return response()->json($entrada);
        }

        $entrada = Inventario::create([
            'user_id'  => auth()->id(),
            'carta_id' => $request->carta_id,
            'cantidad' => $request->cantidad ?? 1,
        ]);

        return response()->json($entrada, 201);
    }

    // Eliminar carta del inventario
    public function destroy($id)
    {
        $entrada = Inventario::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$entrada) {
            return response()->json(['error' => 'Carta no encontrada en tu inventario'], 404);
        }

        $entrada->delete();
        return response()->json(['mensaje' => 'Carta eliminada del inventario']);
    }
}