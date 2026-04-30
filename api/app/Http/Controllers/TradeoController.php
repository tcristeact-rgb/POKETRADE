<?php

namespace App\Http\Controllers;

use App\Models\Tradeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TradeoController extends Controller
{
    // Listar todos los tradeos activos
    public function index()
    {
        $tradeos = Tradeo::with(['usuario', 'cartasOfrece', 'cartasBusca'])
            ->where('estado', 'activo')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tradeos);
    }

    // Ver detalle de un tradeo
    public function show($id)
    {
        $tradeo = Tradeo::with(['usuario', 'cartasOfrece', 'cartasBusca'])->find($id);

        if (!$tradeo) {
            return response()->json(['error' => 'Tradeo no encontrado'], 404);
        }

        return response()->json($tradeo);
    }

    // Crear un tradeo con transacción SQL
    public function store(Request $request)
    {
        $validacion = Validator::make($request->all(), [
            'descripcion'   => 'nullable|string',
            'cartas_ofrece' => 'required|array|min:1',
            'cartas_busca'  => 'required|array|min:1',
        ]);

        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        try {
            DB::beginTransaction();

            $tradeo = Tradeo::create([
                'user_id'     => auth()->id(),
                'descripcion' => $request->descripcion,
                'estado'      => 'activo',
            ]);

            $tradeo->cartasOfrece()->attach($request->cartas_ofrece);
            $tradeo->cartasBusca()->attach($request->cartas_busca);

            DB::commit();

            return response()->json([
                'mensaje' => 'Tradeo publicado correctamente',
                'tradeo'  => $tradeo->load(['cartasOfrece', 'cartasBusca'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Cerrar o cancelar un tradeo
    public function update(Request $request, $id)
    {
        $tradeo = Tradeo::find($id);

        if (!$tradeo) {
            return response()->json(['error' => 'Tradeo no encontrado'], 404);
        }

        if ($tradeo->user_id !== auth()->id()) {
            return response()->json(['error' => 'No tienes permiso para modificar este tradeo'], 403);
        }

        $tradeo->update(['estado' => $request->estado]);

        return response()->json(['mensaje' => 'Tradeo actualizado correctamente']);
    }

    // Eliminar un tradeo
    public function destroy($id)
    {
        $tradeo = Tradeo::find($id);

        if (!$tradeo) {
            return response()->json(['error' => 'Tradeo no encontrado'], 404);
        }

        if ($tradeo->user_id !== auth()->id()) {
            return response()->json(['error' => 'No tienes permiso para eliminar este tradeo'], 403);
        }

        try {
            DB::beginTransaction();
            $tradeo->cartasOfrece()->detach();
            $tradeo->cartasBusca()->detach();
            $tradeo->delete();
            DB::commit();

            return response()->json(['mensaje' => 'Tradeo eliminado correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar el tradeo'], 500);
        }
    }

    // Mis tradeos
    public function misTradeos()
    {
        $tradeos = Tradeo::with(['cartasOfrece', 'cartasBusca'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tradeos);
    }
}