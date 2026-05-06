<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    // Ver perfil del usuario autenticado
    public function perfil()
    {
        return response()->json(auth()->user());
    }

    // Actualizar perfil
    public function actualizarPerfil(Request $request)
    {
        $usuario = auth()->user();

        $validacion = Validator::make($request->all(), [
            'nombre'           => 'nullable|string|max:100',
            'apellido'         => 'nullable|string|max:100',
            'nacionalidad'     => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date',
            'avatar_url'       => 'nullable|string',
        ]);

        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        $usuario->update($request->only([
            'nombre',
            'apellido',
            'nacionalidad',
            'fecha_nacimiento',
            'avatar_url',
        ]));

        return response()->json([
            'mensaje'  => 'Perfil actualizado correctamente',
            'usuario'  => $usuario,
        ]);
    }

    // Cambiar contraseña
    public function cambiarPassword(Request $request)
    {
        $validacion = Validator::make($request->all(), [
            'password_actual' => 'required|string',
            'password_nuevo'  => 'required|string|min:6',
        ]);

        if ($validacion->fails()) {
            return response()->json(['error' => $validacion->errors()->first()], 400);
        }

        $usuario = auth()->user();

        if (!Hash::check($request->password_actual, $usuario->password)) {
            return response()->json(['error' => 'La contraseña actual no es correcta'], 400);
        }

        $usuario->update([
            'password' => Hash::make($request->password_nuevo),
        ]);

        return response()->json(['mensaje' => 'Contraseña actualizada correctamente']);
    }

    // Listar todos los usuarios (solo admin)
    public function index()
    {
        $usuarios = User::select('id', 'nombre', 'apellido', 'email', 'rol', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($usuarios);
    }

    // Eliminar usuario (solo admin)
    public function destroy($id)
    {
        $usuario = User::find($id);

        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $usuario->delete();
        return response()->json(['mensaje' => 'Usuario eliminado correctamente']);
    }
}