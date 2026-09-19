<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carta;
use App\Rules\ClaveTcgValida;
use App\Support\CatalogoTcg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // Para validar los datos recibidos

// Cartas creadas a mano por un administrador (sin tcgdex_id). Es otro público
// y otro middleware que el catálogo: por eso vive aparte del CartaController
// público, que solo lee.
class CartaController extends Controller
{
    // --- Crear una nueva carta ---
    // Endpoint: POST /api/cartas
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    public function store(Request $request)
    {
        // Validamos los datos recibidos
        // Solo el nombre es obligatorio, el resto son opcionales.
        // tipo y rareza se aceptan como CLAVE ('fire') o como el nombre en
        // español o inglés: Rule::in acota a lo que el catálogo conoce, así
        // que una rareza inventada se rechaza en vez de acabar en la BD.
        $validacion = Validator::make($request->all(), [
            'nombre'     => 'required|string',
            'tipo'       => ['nullable', 'string', new ClaveTcgValida('tipo')],
            'rareza'     => ['nullable', 'string', new ClaveTcgValida('rareza')],
            'imagen_url' => 'nullable|string',
        ]);

        // Si la validación falla devolvemos el primer error con código 422
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 422);
        }

        // Los campos que no vengan en el request se quedarán como null
        $carta = Carta::create($request->except(['tipo', 'rareza']) + [
            'tipo_key'   => CatalogoTcg::claveTipo($request->tipo),
            'rareza_key' => CatalogoTcg::claveRareza($request->rareza),
        ]);

        // Devolvemos 201 (creado) con los datos de la carta creada
        return response()->json($carta, 201);
    }

    // --- Actualizar una carta existente ---
    // Endpoint: PUT /api/cartas/{id}
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    //
    // La API lo expone, pero el frontend NO tiene panel de administración: el
    // catálogo lo alimenta TCGdex, no un humano. Esta ruta existe para el rol de
    // admin y para poder corregir una carta a mano si hiciera falta.
    public function update(Request $request, $id)
    {
        // Buscamos la carta por su ID
        $carta = Carta::find($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => __('mensajes.carta_no_encontrada')], 404);
        }

        // Actualizamos solo los campos que vengan en el request
        // Los campos no enviados mantienen su valor actual
        $carta->update($request->except(['tipo', 'rareza']) + array_filter([
            'tipo_key'   => CatalogoTcg::claveTipo($request->tipo),
            'rareza_key' => CatalogoTcg::claveRareza($request->rareza),
        ]));

        // Devolvemos la carta actualizada
        return response()->json($carta);
    }

    // --- Eliminar una carta ---
    // Endpoint: DELETE /api/cartas/{id}
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    // Sin panel en el frontend, igual que update(): existe para el rol de admin.
    public function destroy($id)
    {
        // Buscamos la carta por su ID
        $carta = Carta::find($id);

        // Si no existe devolvemos 404
        if (!$carta) {
            return response()->json(['error' => __('mensajes.carta_no_encontrada')], 404);
        }

        // Eliminamos la carta de la base de datos
        $carta->delete();

        return response()->json(['mensaje' => __('mensajes.carta_eliminada')]);
    }
}
