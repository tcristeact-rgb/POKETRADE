<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;      // Para encriptar y verificar contraseñas
use Illuminate\Support\Facades\Validator; // Para validar los datos recibidos

class UsuarioController extends Controller
{
    // --- Ver perfil del usuario autenticado ---
    // Endpoint: GET /api/usuario/perfil
    // Acceso: protegido (requiere token JWT)
    // Devuelve todos los datos del usuario autenticado
    public function perfil()
    {
        // auth()->user() devuelve el modelo User del usuario autenticado
        // Los campos ocultos en $hidden del modelo (password, remember_token)
        // no se incluyen en la respuesta JSON automáticamente
        return response()->json(auth()->user());
    }

    // --- Actualizar perfil del usuario autenticado ---
    // Endpoint: PUT /api/usuario/perfil
    // Acceso: protegido (requiere token JWT)
    // Solo se pueden modificar los campos permitidos, no el email ni el rol
    public function actualizarPerfil(Request $request)
    {
        // Obtenemos el usuario autenticado
        $usuario = auth()->user();

        // Validamos solo los campos que se pueden actualizar
        // Todos son opcionales (nullable) para permitir actualizaciones parciales
        $validacion = Validator::make($request->all(), [
            'nombre'           => 'nullable|string|max:100',
            'apellido'         => 'nullable|string|max:100',
            'nacionalidad'     => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date',
            'avatar_url'       => 'nullable|string',
        ]);

        // Si la validación falla devolvemos el primer error con código 400
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        // Actualizamos solo los campos permitidos usando only()
        // Esto evita que el usuario pueda modificar campos sensibles
        // como el email, el rol o la contraseña desde este endpoint
        $usuario->update($request->only([
            'nombre',
            'apellido',
            'nacionalidad',
            'fecha_nacimiento',
            'avatar_url',
        ]));

        // Devolvemos mensaje de éxito y los datos actualizados del usuario
        return response()->json([
            'mensaje' => 'Perfil actualizado correctamente',
            'usuario' => $usuario,
        ]);
    }

    // --- Cambiar contraseña ---
    // Endpoint: PUT /api/usuario/password
    // Acceso: protegido (requiere token JWT)
    // Requiere la contraseña actual para verificar la identidad del usuario
    public function cambiarPassword(Request $request)
    {
        // Validamos que vengan los dos campos requeridos
        $validacion = Validator::make($request->all(), [
            'password_actual' => 'required|string',       // Contraseña actual para verificar
            'password_nuevo'  => 'required|string|min:6', // Nueva contraseña, mínimo 6 caracteres
        ]);

        // Si la validación falla devolvemos el primer error con código 400
        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        $usuario = auth()->user();

        // Verificamos que la contraseña actual introducida coincide con la almacenada
        // Hash::check() compara el texto plano con el hash bcrypt de la BD
        // Si no coincide devolvemos error 400
        if (!Hash::check($request->password_actual, $usuario->password)) {
            return response()->json(['error' => 'La contraseña actual no es correcta'], 400);
        }

        // Actualizamos la contraseña encriptándola con bcrypt
        // Nunca se guarda en texto plano
        $usuario->update([
            'password' => Hash::make($request->password_nuevo),
        ]);

        return response()->json(['mensaje' => 'Contraseña actualizada correctamente']);
    }

    // --- Listar todos los usuarios ---
    // Endpoint: GET /api/admin/usuarios
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    // Útil para el panel de administración
    public function index()
    {
        // Seleccionamos solo los campos necesarios para el listado
        // No devolvemos password ni remember_token por seguridad
        $usuarios = User::select('id', 'nombre', 'apellido', 'email', 'rol', 'created_at')
            ->orderBy('created_at', 'desc') // Más recientes primero
            ->get();

        return response()->json($usuarios);
    }

    // --- Eliminar usuario ---
    // Endpoint: DELETE /api/admin/usuarios/{id}
    // Acceso: protegido — solo administradores (middleware EsAdmin)
    // Al eliminar el usuario se eliminan en cascada su inventario y sus tradeos
    // gracias al onDelete('cascade') definido en las migraciones
    public function destroy($id)
    {
        // Buscamos el usuario por ID
        $usuario = User::find($id);

        // Si no existe devolvemos 404
        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Eliminamos el usuario
        // Por el cascade definido en las migraciones también se eliminan
        // automáticamente su inventario y sus tradeos
        $usuario->delete();

        return response()->json(['mensaje' => 'Usuario eliminado correctamente']);
    }
}