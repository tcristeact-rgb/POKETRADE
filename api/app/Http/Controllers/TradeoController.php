<?php

namespace App\Http\Controllers;

use App\Models\Tradeo;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;        // Para usar transacciones SQL
use Illuminate\Support\Facades\Validator; // Para validar los datos recibidos

class TradeoController extends Controller
{
    // --- Listar todos los tradeos activos ---
    // Endpoint: GET /api/tradeos
    // Acceso: público (sin token)
    // Devuelve solo los tradeos con estado 'activo', ordenados del más reciente al más antiguo
    public function index()
    {
        // with() carga las relaciones en una sola consulta (eager loading)
        // Evita el problema N+1 queries al cargar usuario y cartas de cada tradeo
        $tradeos = Tradeo::with(['usuario', 'cartasOfrece', 'cartasBusca'])
            ->where('estado', 'activo')          // Solo tradeos activos
            ->orderBy('created_at', 'desc')      // Más recientes primero
            ->get();

        return response()->json($tradeos);
    }

    // --- Ver detalle de un tradeo ---
    // Endpoint: GET /api/tradeos/{id}
    // Acceso: público (sin token)
    public function show($id)
    {
        // Buscamos el tradeo por ID cargando todas sus relaciones
        $tradeo = Tradeo::with(['usuario', 'cartasOfrece', 'cartasBusca'])->find($id);

        // Si no existe devolvemos 404
        if (!$tradeo) {
            return response()->json(['error' => 'Tradeo no encontrado'], 404);
        }

        return response()->json($tradeo);
    }

    // --- Crear un tradeo ---
    // Endpoint: POST /api/tradeos
    // Acceso: protegido (requiere token JWT)
    // Usa transacción SQL para garantizar que si algo falla no queden datos a medias
    public function store(Request $request)
    {
        // Validamos los datos recibidos
        $validacion = Validator::make($request->all(), [
            'descripcion'   => 'nullable|string',          // Descripción opcional
            'cartas_ofrece' => 'required|array|min:1',     // Al menos 1 carta ofrecida
            'cartas_busca'  => 'required|array|min:1',     // Al menos 1 carta buscada
        ]);

        // Si la validación falla devolvemos el primer error con código 400
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        try {
            // Iniciamos la transacción — si algo falla se revierten todos los cambios
            DB::beginTransaction();

            // Creamos el tradeo principal
            $tradeo = Tradeo::create([
                'user_id'     => auth()->id(),        // ID del usuario autenticado
                'descripcion' => $request->descripcion,
                'estado'      => 'activo',            // Todo tradeo nuevo empieza activo
            ]);

            // Asociamos las cartas que ofrece en la tabla pivote tradeo_cartas_ofrece
            // attach() recibe un array de IDs y crea las filas en la tabla pivote
            $tradeo->cartasOfrece()->attach($request->cartas_ofrece);

            // Asociamos las cartas que busca en la tabla pivote tradeo_cartas_busca
            $tradeo->cartasBusca()->attach($request->cartas_busca);

            // Si todo fue bien confirmamos la transacción
            DB::commit();

            // Devolvemos 201 con el tradeo completo incluyendo las cartas asociadas
            // load() recarga las relaciones después de hacer el attach()
            return response()->json([
                'mensaje' => 'Tradeo publicado correctamente',
                'tradeo'  => $tradeo->load(['cartasOfrece', 'cartasBusca'])
            ], 201);

        } catch (\Exception $e) {
            // Si algo falla revertimos todos los cambios de la transacción
            // Así no quedan tradeos sin cartas o cartas huérfanas en las tablas pivote
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // --- Actualizar estado de un tradeo ---
    // Endpoint: PUT /api/tradeos/{id}
    // Acceso: protegido (requiere token JWT)
    // Solo el propietario puede modificar su tradeo (cerrar o cancelar)
    public function update(Request $request, $id)
    {
        // Buscamos el tradeo por ID
        $tradeo = Tradeo::find($id);

        // Si no existe devolvemos 404
        if (!$tradeo) {
            return response()->json(['error' => 'Tradeo no encontrado'], 404);
        }

        // Comprobamos que el tradeo pertenece al usuario autenticado
        // Si no es el propietario devolvemos 403 (prohibido)
        if ($tradeo->user_id !== auth()->id()) {
            return response()->json(['error' => 'No tienes permiso para modificar este tradeo'], 403);
        }

        // Actualizamos el estado del tradeo (ej: 'cerrado' o 'cancelado')
        $tradeo->update(['estado' => $request->estado]);

        return response()->json(['mensaje' => 'Tradeo actualizado correctamente']);
    }

    // --- Eliminar un tradeo ---
    // Endpoint: DELETE /api/tradeos/{id}
    // Acceso: protegido (requiere token JWT)
    // Solo el propietario puede eliminar su tradeo
    // Usa transacción para eliminar primero las tablas pivote y luego el tradeo
    public function destroy($id)
    {
        // Buscamos el tradeo por ID
        $tradeo = Tradeo::find($id);

        // Si no existe devolvemos 404
        if (!$tradeo) {
            return response()->json(['error' => 'Tradeo no encontrado'], 404);
        }

        // Comprobamos que el tradeo pertenece al usuario autenticado
        if ($tradeo->user_id !== auth()->id()) {
            return response()->json(['error' => 'No tienes permiso para eliminar este tradeo'], 403);
        }

        try {
            // Iniciamos la transacción
            DB::beginTransaction();

            // Primero eliminamos las filas de las tablas pivote
            // Si no se hace antes, la BD lanzaría un error de integridad referencial
            $tradeo->cartasOfrece()->detach(); // Elimina filas de tradeo_cartas_ofrece
            $tradeo->cartasBusca()->detach();  // Elimina filas de tradeo_cartas_busca

            // Ahora eliminamos el tradeo principal
            $tradeo->delete();

            // Confirmamos la transacción
            DB::commit();

            return response()->json(['mensaje' => 'Tradeo eliminado correctamente']);

        } catch (\Exception $e) {
            // Si algo falla revertimos todos los cambios
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar el tradeo'], 500);
        }
    }

    // --- Ver mis tradeos ---
    // Endpoint: GET /api/mis-tradeos
    // Acceso: protegido (requiere token JWT)
    // Devuelve todos los tradeos del usuario autenticado (activos, cerrados y cancelados)
    public function misTradeos()
    {
        // A diferencia de index(), aquí devolvemos TODOS los estados
        // para que el usuario pueda ver su historial completo
        $tradeos = Tradeo::with(['cartasOfrece', 'cartasBusca'])
            ->where('user_id', auth()->id()) // Solo los tradeos del usuario autenticado
            ->orderBy('created_at', 'desc')  // Más recientes primero
            ->get();

        return response()->json($tradeos);
    }

    // --- Aceptar un tradeo ---
    // Endpoint: POST /api/tradeos/{id}/aceptar
    // Acceso: protegido (requiere token JWT)
    // Intercambia las cartas entre creador y aceptante y cierra el tradeo
    public function aceptar($id)
    {
        $tradeo = Tradeo::with(['cartasOfrece', 'cartasBusca'])->find($id);

        if (!$tradeo) {
            return response()->json(['error' => 'Tradeo no encontrado'], 404);
        }
        if ($tradeo->estado !== 'activo') {
            return response()->json(['error' => 'Este tradeo ya no está disponible'], 409);
        }
        if ($tradeo->user_id === auth()->id()) {
            return response()->json(['error' => 'No puedes aceptar tu propio tradeo'], 403);
        }

        try {
            DB::beginTransaction();

            $aceptanteId = auth()->id();
            $creadorId   = $tradeo->user_id;

            // 1. Quitar cartas_busca del inventario del aceptante
            foreach ($tradeo->cartasBusca as $carta) {
                $eliminado = Inventario::where('user_id', $aceptanteId)
                    ->where('carta_id', $carta->id)
                    ->delete();
                if (!$eliminado) {
                    DB::rollBack();
                    return response()->json(['error' => "No tienes la carta requerida: {$carta->nombre}"], 422);
                }
            }

            // 2. Añadir cartas_ofrece al inventario del aceptante
            foreach ($tradeo->cartasOfrece as $carta) {
                $entrada = Inventario::where('user_id', $aceptanteId)
                    ->where('carta_id', $carta->id)->first();
                if ($entrada) {
                    $entrada->increment('cantidad');
                } else {
                    Inventario::create(['user_id' => $aceptanteId, 'carta_id' => $carta->id, 'cantidad' => 1]);
                }
            }

            // 3. Añadir cartas_busca al inventario del creador
            foreach ($tradeo->cartasBusca as $carta) {
                $entrada = Inventario::where('user_id', $creadorId)
                    ->where('carta_id', $carta->id)->first();
                if ($entrada) {
                    $entrada->increment('cantidad');
                } else {
                    Inventario::create(['user_id' => $creadorId, 'carta_id' => $carta->id, 'cantidad' => 1]);
                }
            }

            // 4. Cerrar el tradeo para que desaparezca del marketplace
            $tradeo->update(['estado' => 'cerrado']);

            DB::commit();

            return response()->json(['mensaje' => 'Intercambio completado correctamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error durante el intercambio: ' . $e->getMessage()], 500);
        }
    }
}
