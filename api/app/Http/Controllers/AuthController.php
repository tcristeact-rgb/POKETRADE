<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;      // Para autenticación y generación de token JWT
use Illuminate\Support\Facades\Hash;      // Para encriptar contraseñas
use Illuminate\Support\Facades\Validator; // Para validar los datos recibidos

class AuthController extends Controller
{
    // Hash bcrypt real (coste 12, el BCRYPT_ROUNDS de .env.example) contra el que
    // se compara la contraseña cuando el email NO existe. Sin esto, un login con
    // email desconocido responde en milisegundos y uno con email registrado tarda
    // lo que tarda bcrypt (~250 ms): la latencia delata qué emails están dados de
    // alta. El JWTGuard de tymon no usa el Timebox de Laravel, así que se iguala
    // aquí. Generado una vez con password_hash('…', PASSWORD_BCRYPT, ['cost' => 12]).
    private const HASH_DUMMY = '$2y$12$CMwcSYzKmRZ1bkY38b6X.eTppLzIq2eV23kr3l205GPB7HVyAnKt.';

    // --- Registro de nuevo usuario ---
    // Endpoint: POST /api/auth/registro
    // Acceso: público (sin token)
    
    public function registro(Request $request)
    {
        // Validamos los datos recibidos en el body de la petición
        // Si alguna regla falla, se devuelve el primer error encontrado
        $validacion = Validator::make($request->all(), [
            'nombre'           => 'required|string|max:100',  // Obligatorio, máx 100 caracteres
            'apellido'         => 'required|string|max:100',  // Obligatorio, máx 100 caracteres
            'email'            => 'required|string|email|max:255|unique:users,email', // Obligatorio, formato email, cabe en la columna y único
            'password'         => 'required|string|min:8',    // Obligatorio, mínimo 8 caracteres
            'fecha_nacimiento' => 'nullable|date',            // Opcional, formato fecha
            'nacionalidad'     => 'nullable|string|max:100',  // Opcional, máx 100 caracteres
        ]);

        // Si la validación falla devolvemos el primer error con código 422
        if ($validacion->fails()) {
            return response()->json([
                'error' => $validacion->errors()->first()
            ], 422);
        }

        // Creamos el usuario en la base de datos
        // La contraseña se encripta con bcrypt mediante Hash::make()
        // nunca se guarda en texto plano
        $usuario = User::create([
            'nombre'           => $request->nombre,
            'apellido'         => $request->apellido,
            'email'            => $request->email,
            'password'         => Hash::make($request->password),
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'nacionalidad'     => $request->nacionalidad,
            'rol'              => 'cliente', // Todo usuario nuevo es cliente por defecto
        ]);

        // Devolvemos 201 (creado) con mensaje de éxito e ID del nuevo usuario
        return response()->json([
            'mensaje' => __('mensajes.registrado'),
            'id'      => $usuario->id
        ], 201);
    }

    // --- Inicio de sesión ---
    // Endpoint: POST /api/auth/login
    // Acceso: público (sin token)
    // Devuelve un token JWT para usar en las siguientes peticiones protegidas
    public function login(Request $request)
    {
        // Validamos antes de tocar Auth::attempt(): sin la clave `password`, o con
        // ella como array, el proveedor de usuarios revienta con un 500. Debe ser
        // un 422 como en el resto de la API.
        $validacion = Validator::make($request->all(), [
            'email'    => 'required|string|email|max:255',
            'password' => 'required|string',
        ]);

        if ($validacion->fails()) {
            return response()->json([
                'error' => $validacion->errors()->first()
            ], 422);
        }

        $credenciales = $validacion->validated();

        // Auth::attempt() comprueba las credenciales contra la BD
        // Si son correctas genera y devuelve el token JWT
        // Si son incorrectas devuelve false
        if (!$token = Auth::attempt($credenciales)) {
            // Si el email no existe, attempt() no ha ejecutado bcrypt. Lo ejecutamos
            // contra un hash dummy para que la respuesta tarde lo mismo que con un
            // email registrado y la latencia no revele qué cuentas existen.
            if (!User::where('email', $credenciales['email'])->exists()) {
                Hash::check($credenciales['password'], self::HASH_DUMMY);
            }

            return response()->json([
                'error' => __('mensajes.credenciales')
            ], 401);
        }

        // Obtenemos los datos del usuario autenticado
        $usuario = Auth::user();

        // Devolvemos el token JWT y los datos básicos del usuario
        // El frontend guardará este token para enviarlo en peticiones protegidas
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

    // --- Cerrar sesión ---
    // Endpoint: POST /api/auth/logout
    // Acceso: protegido (requiere token JWT)
    // Invalida el token actual para que no pueda seguir usándose
    public function logout()
    {
        Auth::logout(); // Invalida el token JWT del usuario autenticado
        return response()->json(['mensaje' => __('mensajes.sesion_cerrada')]);
    }
}