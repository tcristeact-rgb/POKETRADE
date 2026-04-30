<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Registro de nuevo usuario
    public function registro(Request $request)
    {
        // Validar los datos recibidos
        $validacion = Validator::make($request->all(), [
            'nombre'           => 'required|string|max:100',
            'apellido'         => 'required|string|max:100',
            'email'            => 'required|email|unique:users,email',
            'password'         => 'required|string|min:6',
            'fecha_nacimiento' => 'nullable|date',
            'nacionalidad'     => 'nullable|string|max:100',
        ]);

        if ($validacion->fails()) {
            return response()->json([
                'error' => $validacion->errors()->first()
            ], 400);
        }

        // Crear el usuario
        $usuario = User::create([
            'nombre'           => $request->nombre,
            'apellido'         => $request->apellido,
            'email'            => $request->email,
            'password'         => Hash::make($request->password),
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'nacionalidad'     => $request->nacionalidad,
            'rol'              => 'cliente',
        ]);

        return response()->json([
            'mensaje' => 'Usuario registrado correctamente',
            'id'      => $usuario->id
        ], 201);
    }

    // Inicio de sesión
    public function login(Request $request)
    {
        $credenciales = $request->only('email', 'password');

        if (!$token = Auth::attempt($credenciales)) {
            return response()->json([
                'error' => 'Email o contraseña incorrectos'
            ], 400);
        }

        $usuario = Auth::user();

        return response()->json([
            'token'   => $token,
            'usuario' => [
                'id'       => $usuario->id,
                'nombre'   => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'email'    => $usuario->email,
            ]
        ]);
    }

    // Cerrar sesión
    public function logout()
    {
        Auth::logout();
        return response()->json(['mensaje' => 'Sesión cerrada correctamente']);
    }
}