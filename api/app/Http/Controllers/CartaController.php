<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartaController extends Controller
{
    // Obtener todas las cartas con filtros opcionales
    public function index(Request $request)
    {
        $query = Carta::query();

        // Filtrar por nombre
        if ($request->has('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        // Filtrar por tipo
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        // Filtrar por rareza
        if ($request->has('rareza')) {
            $query->where('rareza', $request->rareza);
        }

        // Filtrar por set
        if ($request->has('set')) {
            $query->where('set_expansion', $request->set);
        }

        return response()->json($query->get());
    }

    // Obtener una carta por id
    public function show($id)
    {
        $carta = Carta::find($id);

        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        return response()->json($carta);
    }

        // Crear carta (solo admin)
    public function store(Request $request)
    {
        $validacion = Validator::make($request->all(), [
            'nombre'    => 'required|string',
            'tipo'      => 'nullable|string',
            'rareza'    => 'nullable|string',
            'imagen_url'=> 'nullable|string',
        ]);

        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        $carta = Carta::create($request->all());
        return response()->json($carta, 201);
    }

    // Actualizar carta (solo admin)
    public function update(Request $request, $id)
    {
        $carta = Carta::find($id);
        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        $carta->update($request->all());
        return response()->json($carta);
    }

    // Eliminar carta (solo admin)
    public function destroy($id)
    {
        $carta = Carta::find($id);
        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        $carta->delete();
        return response()->json(['mensaje' => 'Carta eliminada correctamente']);
    }
}