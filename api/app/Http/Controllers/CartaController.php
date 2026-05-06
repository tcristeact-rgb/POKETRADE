<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // Para validar los datos recibidos

class CartaController extends Controller
{
    // --- Listar todas las cartas con filtros opcionales ---
    // Endpoint: GET /api/cartas
    // Acceso: público (sin token)
    // Parámetros opcionales de query: ?nombre=X &tipo=X &rareza=X &set=X
    public function index(Request $request)
    {
        // Iniciamos una query base sobre la tabla cartas
        // Iremos añadiendo filtros dinámicamente según los parámetros recibidos
        $query = Carta::query();

        // Filtro por nombre — búsqueda parcial con LIKE
        // Ejemplo: ?nombre=char → devuelve Charizard
        if ($request->has('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        // Filtro por tipo — búsqueda exacta
        // Ejemplo: ?tipo=Fuego → devuelve solo cartas de tipo Fuego
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        // Filtro por rareza — búsqueda exacta
        // Ejemplo: ?rareza=Ultra Rara → devuelve solo cartas Ultra Raras
        if ($request->has('rareza')) {
            $query->where('rareza', $request->rareza);
        }

        // Filtro por set de expansión — búsqueda exacta
        // Ejemplo: ?set=Fossil → devuelve solo cartas del set Fossil
        if ($request->has('set')) {
            $query->where('set_expansion', $request->set);
        }

        // Ejecutamos la query con los filtros aplicados y devolvemos el resultado
        return response()->json($query->get());
    }

    // --- Ver detalle de una carta ---
    // Endpoint: GET /api/cartas/{id}
    // Acceso: público (sin token)
    public function show($id)
    {
        // Buscamos la carta por su ID
        $carta = Carta::find($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        return response()->json($carta);
    }

    // --- Crear una nueva carta ---
    // Endpoint: POST /api/cartas
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    public function store(Request $request)
    {
        // Validamos los datos recibidos
        // Solo el nombre es obligatorio, el resto son opcionales
        $validacion = Validator::make($request->all(), [
            'nombre'     => 'required|string',  // Obligatorio
            'tipo'       => 'nullable|string',  // Opcional
            'rareza'     => 'nullable|string',  // Opcional
            'imagen_url' => 'nullable|string',  // Opcional
        ]);

        // Si la validación falla devolvemos el primer error con código 400
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        // Creamos la carta con todos los datos recibidos
        // Los campos que no vengan en el request se quedarán como null
        $carta = Carta::create($request->all());

        // Devolvemos 201 (creado) con los datos de la carta creada
        return response()->json($carta, 201);
    }

    // --- Actualizar una carta existente ---
    // Endpoint: PUT /api/cartas/{id}
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    public function update(Request $request, $id)
    {
        // Buscamos la carta por su ID
        $carta = Carta::find($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        // Actualizamos solo los campos que vengan en el request
        // Los campos no enviados mantienen su valor actual
        $carta->update($request->all());

        // Devolvemos la carta actualizada
        return response()->json($carta);
    }

    // --- Eliminar una carta ---
    // Endpoint: DELETE /api/cartas/{id}
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    public function destroy($id)
    {
        // Buscamos la carta por su ID
        $carta = Carta::find($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => 'Carta no encontrada'], 404);
        }

        // Eliminamos la carta de la base de datos
        $carta->delete();

        return response()->json(['mensaje' => 'Carta eliminada correctamente']);
    }
}