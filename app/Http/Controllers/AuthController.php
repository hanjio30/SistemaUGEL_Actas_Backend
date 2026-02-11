<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'usuario' => 'required|string',
            'contrasena' => 'required|string',
        ]);

        $usuario = Usuario::where('usuario', $request->usuario)->first();

        // Verificar si el usuario existe
        if (!$usuario) {
            throw ValidationException::withMessages([
                'usuario' => ['Usuario no encontrado.'],
            ]);
        }

        // ⭐ NUEVA VALIDACIÓN: Verificar si el usuario está activo usando el método del modelo
        if (!$usuario->estaActivo()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo. Contacte al administrador del sistema.',
            ], 403);
        }

        // Verificar contraseña
        if (!Hash::check($request->contrasena, $usuario->contrasena)) {
            throw ValidationException::withMessages([
                'usuario' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // ⭐ NUEVA FUNCIÓN: Actualizar último acceso usando el método del modelo
        $usuario->actualizarUltimoAcceso();

        // Guardar en sesión con todos los campos nuevos
        session([
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->nombre_completo,
            'usuario_usuario' => $usuario->usuario,
            'usuario_correo' => $usuario->correo,
            'usuario_rol' => $usuario->rol,
            'usuario_dni' => $usuario->dni,
            'usuario_telefono' => $usuario->telefono,
            'usuario_foto' => $usuario->foto_perfil,
            'usuario_es_admin' => $usuario->esAdministrador(), // Usar método del modelo
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'data' => [
                'id' => $usuario->id,
                'nombre_completo' => $usuario->nombre_completo,
                'dni' => $usuario->dni,
                'telefono' => $usuario->telefono,
                'usuario' => $usuario->usuario,
                'correo' => $usuario->correo,
                'rol' => $usuario->rol,
                'estado' => $usuario->estado,
                'foto_perfil' => $usuario->foto_perfil,
                'foto_perfil_url' => $usuario->foto_perfil_url, // Avatar generado automáticamente
                'iniciales' => $usuario->iniciales, // Iniciales del nombre
                'ultimo_acceso' => $usuario->ultimo_acceso,
                'es_administrador' => $usuario->esAdministrador(),
                'notificaciones_no_leidas' => $usuario->contarNoLeidas(), // Contador de notificaciones
            ]
        ], 200);
    }

    public function usuarioActual(Request $request)
    {
        // Obtener usuario de la sesión
        $usuarioId = session('usuario_id');

        if (!$usuarioId) {
            return response()->json([
                'error' => 'No hay usuario autenticado'
            ], 401);
        }

        // Cargar el usuario completo desde la base de datos
        $usuario = Usuario::find($usuarioId);

        if (!$usuario) {
            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }

        // Verificar si sigue activo
        if (!$usuario->estaActivo()) {
            session()->flush();
            return response()->json([
                'error' => 'Usuario inactivo. Su sesión ha sido cerrada.'
            ], 403);
        }

        return response()->json([
            'id' => $usuario->id,
            'nombre_completo' => $usuario->nombre_completo,
            'usuario' => $usuario->usuario,
            'correo' => $usuario->correo,
            'rol' => $usuario->rol,
            'dni' => $usuario->dni,
            'telefono' => $usuario->telefono,
            'foto_perfil' => $usuario->foto_perfil,
            'foto_perfil_url' => $usuario->foto_perfil_url,
            'iniciales' => $usuario->iniciales,
            'es_administrador' => $usuario->esAdministrador(),
            'notificaciones_no_leidas' => $usuario->contarNoLeidas(),
            'tiempo_ultimo_acceso' => $usuario->tiempo_ultimo_acceso,
        ]);
    }

    public function logout(Request $request)
    {
        // Limpiar sesión
        session()->flush();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ], 200);
    }
}