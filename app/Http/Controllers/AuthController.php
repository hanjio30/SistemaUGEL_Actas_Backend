<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_INTENTOS = 3;
    private const MINUTOS_BLOQUEO = 15;
    private const SEGUNDOS_INACTIVIDAD = 2 * 60 * 60; // 2 horas

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

        // Verificar si el usuario está activo
        if (!$usuario->estaActivo()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo. Contacte al administrador del sistema.',
            ], 403);
        }

        // ⭐ 1. ¿Bloqueo vigente?
        if ($usuario->bloqueado_hasta) {
            $hasta = Carbon::parse($usuario->bloqueado_hasta);

            if (now()->lt($hasta)) {
                $segundos = max(1, (int) ceil(now()->diffInSeconds($hasta, false)));
                $minutos = (int) ceil($segundos / 60);

                return response()->json([
                    'success' => false,
                    'message' => "Cuenta bloqueada temporalmente por intentos fallidos. Intente nuevamente en {$minutos} minuto(s).",
                    'retry_after' => $segundos,
                ], 429);
            }

            // El bloqueo ya venció: reiniciar
            $usuario->forceFill([
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();
        }

        // ⭐ 2. Verificar contraseña
        if (!Hash::check($request->contrasena, $usuario->contrasena)) {
            $intentos = $usuario->intentos_fallidos + 1;

            if ($intentos >= self::MAX_INTENTOS) {
                $usuario->forceFill([
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => now()->addMinutes(self::MINUTOS_BLOQUEO),
                ])->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Superó los ' . self::MAX_INTENTOS . ' intentos permitidos. Su cuenta fue bloqueada por ' . self::MINUTOS_BLOQUEO . ' minutos.',
                    'retry_after' => self::MINUTOS_BLOQUEO * 60,
                ], 429);
            }

            $usuario->forceFill(['intentos_fallidos' => $intentos])->save();
            $restantes = self::MAX_INTENTOS - $intentos;

            throw ValidationException::withMessages([
                'usuario' => ["Credenciales incorrectas. Le quedan {$restantes} intento(s)."],
            ]);
        }

        // ⭐ 3. Login correcto: reiniciar contadores
        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        $usuario->actualizarUltimoAcceso();

        // Regenerar ID de sesión (evita session fixation)
        session()->regenerate();

        session([
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->nombre_completo,
            'usuario_usuario' => $usuario->usuario,
            'usuario_correo' => $usuario->correo,
            'usuario_rol' => $usuario->rol,
            'usuario_dni' => $usuario->dni,
            'usuario_telefono' => $usuario->telefono,
            'usuario_foto' => $usuario->foto_perfil,
            'usuario_es_admin' => $usuario->esAdministrador(),
            'ultima_actividad' => time(), // ⭐ control de inactividad
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
                'foto_perfil_url' => $usuario->foto_perfil_url,
                'iniciales' => $usuario->iniciales,
                'ultimo_acceso' => $usuario->ultimo_acceso,
                'es_administrador' => $usuario->esAdministrador(),
                'notificaciones_no_leidas' => $usuario->contarNoLeidas(),
            ]
        ], 200);
    }

    public function usuarioActual(Request $request)
    {
        $usuarioId = session('usuario_id');

        if (!$usuarioId) {
            return response()->json([
                'error' => 'No hay usuario autenticado'
            ], 401);
        }

        // ⭐ Control de inactividad (2 horas)
        $ultima = session('ultima_actividad', time());

        if (time() - $ultima > self::SEGUNDOS_INACTIVIDAD) {
            session()->flush();
            return response()->json([
                'error' => 'Su sesión expiró por inactividad.',
                'expired' => true,
            ], 401);
        }

        // Renovar marca de actividad
        session(['ultima_actividad' => time()]);

        $usuario = Usuario::find($usuarioId);

        if (!$usuario) {
            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }

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
        session()->flush();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ], 200);
    }
}