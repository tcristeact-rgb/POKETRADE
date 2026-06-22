<?php

namespace App\Http\Controllers;

use App\Models\Tradeo;
use App\Models\Inventario;
use App\Models\Carta;
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

        // Si la validación falla devolvemos el primer error con código 422
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 422);
        }

        try {
            // Iniciamos la transacción — si algo falla se revierten todos los cambios
            DB::beginTransaction();

            // Retiramos del inventario del usuario las cartas que va a ofrecer,
            // dentro de la misma transacción que la creación del tradeo. Se
            // verifica la propiedad y se descuenta por cantidad (no se borra la
            // fila entera si el usuario tiene varias copias de la carta).
            foreach ($request->cartas_ofrece as $cartaId) {
                $entrada = Inventario::where('user_id', auth()->id())
                    ->where('carta_id', $cartaId)
                    ->first();

                if (!$entrada) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'No tienes en tu inventario alguna de las cartas que intentas ofrecer.'
                    ], 422);
                }

                if ($entrada->cantidad > 1) {
                    $entrada->decrement('cantidad');
                } else {
                    $entrada->delete();
                }
            }

            // Creamos el tradeo principal
            $tradeo = Tradeo::create([
                'user_id'     => auth()->id(),        // ID del usuario autenticado
                'descripcion' => $request->descripcion,
                'estado'      => 'activo',            // Todo tradeo nuevo empieza activo
            ]);

            // Asociamos las cartas que ofrece en la tabla pivote tradeo_cartas_ofrece
            $tradeo->cartasOfrece()->attach($request->cartas_ofrece);

            // Las cartas buscadas pueden llegar como datos completos de Pokémon
            // de la PokeAPI (el catálogo del backend solo tiene unas pocas).
            // Las resolvemos a IDs de la tabla cartas, creando las que falten.
            $tradeo->cartasBusca()->attach($this->resolverCartasBuscadas($request->cartas_busca));

            // Si todo fue bien confirmamos la transacción
            DB::commit();

            // Devolvemos 201 con el tradeo completo incluyendo las cartas asociadas
            return response()->json([
                'mensaje' => 'Tradeo publicado correctamente',
                'tradeo'  => $tradeo->load(['cartasOfrece', 'cartasBusca'])
            ], 201);

        } catch (\InvalidArgumentException $e) {
            // Datos de una carta buscada incompletos: error del cliente
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            // Si algo falla revertimos todos los cambios de la transacción
            DB::rollBack();
            return response()->json(['error' => 'No se pudo publicar el tradeo.'], 500);
        }
    }

    // Convierte la lista de cartas buscadas en IDs de la tabla cartas.
    // Cada elemento puede ser un ID (carta ya existente) o un objeto con los
    // datos de un Pokémon de la PokeAPI, que se crea si no existe (por numero).
    private function resolverCartasBuscadas(array $cartas): array
    {
        $ids = [];
        foreach ($cartas as $c) {
            if (is_array($c)) {
                if (empty($c['numero'])) {
                    throw new \InvalidArgumentException('Falta el número de Pokédex de una carta buscada.');
                }
                $carta = Carta::firstOrCreate(
                    ['numero' => $c['numero']],
                    [
                        'nombre'        => $c['nombre']        ?? 'Carta',
                        'tipo'          => $c['tipo']          ?? null,
                        'rareza'        => $c['rareza']        ?? null,
                        'set_expansion' => $c['set_expansion'] ?? null,
                        'imagen_url'    => $c['imagen_url']    ?? null,
                    ]
                );
                $ids[] = $carta->id;
            } else {
                $ids[] = $c;
            }
        }
        return $ids;
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

        // Validamos que el estado recibido sea uno de los permitidos
        $validacion = Validator::make($request->all(), [
            'estado' => 'required|in:cerrado,cancelado',
        ]);
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 422);
        }

        // Actualizamos el estado del tradeo ('cerrado' o 'cancelado')
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

            // Bloqueamos la fila del tradeo y la recargamos dentro de la transacción.
            // La comprobación de estado anterior es solo un filtro rápido sin bloqueo:
            // dos usuarios podrían pasarla a la vez. lockForUpdate() serializa el acceso
            // a esta fila, de modo que el segundo en entrar espera al primero y, al
            // re-verificar el estado, ve que el tradeo ya está cerrado y se detiene.
            $tradeo = Tradeo::with(['cartasOfrece', 'cartasBusca'])
                ->lockForUpdate()
                ->find($id);

            if (!$tradeo || $tradeo->estado !== 'activo') {
                DB::rollBack();
                return response()->json(['error' => 'Este tradeo ya no está disponible'], 409);
            }

            $aceptanteId = auth()->id();
            $creadorId   = $tradeo->user_id;

            // 1. Quitar cartas_busca del inventario del aceptante.
            //    Se descuenta por cantidad: si tiene varias copias se resta una
            //    y solo se borra la fila cuando se queda sin copias.
            foreach ($tradeo->cartasBusca as $carta) {
                $entrada = Inventario::where('user_id', $aceptanteId)
                    ->where('carta_id', $carta->id)
                    ->first();
                if (!$entrada) {
                    DB::rollBack();
                    return response()->json(['error' => "No tienes la carta requerida: {$carta->nombre}"], 422);
                }
                if ($entrada->cantidad > 1) {
                    $entrada->decrement('cantidad');
                } else {
                    $entrada->delete();
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
            return response()->json(['error' => 'No se pudo completar el intercambio.'], 500);
        }
    }
}
