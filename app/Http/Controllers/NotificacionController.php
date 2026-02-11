<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Expediente;
use App\Models\Notificacion;
use App\Models\Usuario;

class NotificacionController extends Controller
{
    /**
     * Enviar notificaciones sobre expedientes próximos a vencer
     * Envía EMAIL + Guarda en BASE DE DATOS
     */
    public function notificarVencimientos(Request $request)
    {
        try {
            $expedientes = $request->validate([
                'expedientes' => 'required|array',
                'expedientes.*.id' => 'required|integer',
                'expedientes.*.num_expediente' => 'required|string',
                'expedientes.*.dias' => 'required|integer',
                'expedientes.*.solicitante' => 'nullable|string',
                'expedientes.*.observaciones' => 'nullable|string'
            ]);

            // Obtener TODOS los usuarios
            $usuariosANotificar = DB::table('usuario')
                ->select('id', 'usuario as name', 'correo as email', 'rol')
                ->get();

            if ($usuariosANotificar->isEmpty()) {
                return response()->json([
                    'warning' => 'No hay usuarios registrados para enviar notificaciones',
                    'expedientes_procesados' => count($expedientes['expedientes'])
                ], 200);
            }

            $notificacionesEnviadas = 0;
            $notificacionesGuardadas = 0;
            $errores = [];

            // Preparar el mensaje para la BD
            $cantidad = count($expedientes['expedientes']);
            $titulo = "⚠️ Expedientes Observados Próximos a Vencer";
            $mensaje = "Hay {$cantidad} expediente" . ($cantidad != 1 ? 's' : '') . " observado" . ($cantidad != 1 ? 's' : '') . " que requiere" . ($cantidad != 1 ? 'n' : '') . " atención urgente.";

            foreach ($usuariosANotificar as $usuario) {
                try {
                    // 1. ENVIAR EMAIL (si tiene correo)
                    if (!empty($usuario->email)) {
                        $this->enviarEmail($usuario, $expedientes['expedientes']);
                        $notificacionesEnviadas++;
                        Log::info("Email enviado a: {$usuario->email} ({$usuario->rol})");
                    }

                    // 2. GUARDAR EN BASE DE DATOS
                    Notificacion::create([
                        'usuario_id' => $usuario->id,
                        'tipo' => 'vencimientos_observados',
                        'titulo' => $titulo,
                        'mensaje' => $mensaje,
                        'datos' => [
                            'expedientes' => $expedientes['expedientes'],
                            'cantidad' => $cantidad,
                            'fecha_envio' => now()->toDateTimeString()
                        ],
                        'prioridad' => $cantidad >= 5 ? 'urgente' : 'alta',
                        'leida' => false
                    ]);
                    $notificacionesGuardadas++;
                    Log::info("Notificación BD guardada para usuario: {$usuario->id}");

                } catch (\Exception $e) {
                    $errores[] = [
                        'usuario' => $usuario->email ?? $usuario->name,
                        'rol' => $usuario->rol,
                        'error' => $e->getMessage()
                    ];
                    Log::error("Error al notificar a {$usuario->email}: " . $e->getMessage());
                }
            }

            // Registrar resumen
            Log::info('Notificaciones de vencimientos procesadas', [
                'cantidad_expedientes' => $cantidad,
                'emails_enviados' => $notificacionesEnviadas,
                'notificaciones_guardadas' => $notificacionesGuardadas,
                'total_usuarios' => $usuariosANotificar->count(),
                'errores' => count($errores)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificaciones enviadas y guardadas correctamente',
                'emails_enviados' => $notificacionesEnviadas,
                'notificaciones_guardadas' => $notificacionesGuardadas,
                'total_usuarios' => $usuariosANotificar->count(),
                'expedientes_procesados' => $cantidad,
                'errores' => $errores
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Datos inválidos',
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al procesar notificaciones de vencimientos: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al procesar notificaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar email con lista de expedientes próximos a vencer
     */
    private function enviarEmail($usuario, $expedientes)
    {
        $datos = [
            'usuario' => $usuario->name ?? 'Usuario',
            'rol' => $usuario->rol ?? 'Usuario',
            'expedientes' => $expedientes,
            'cantidad' => count($expedientes),
            'fecha' => date('d/m/Y H:i')
        ];

        Mail::send('emails.vencimientos', $datos, function($message) use ($usuario) {
            $message->to($usuario->email)
                    ->subject('⚠️ ALERTA: Expedientes Observados Próximos a Vencer - UGEL');
        });
    }

    /**
     * Obtener notificaciones de un usuario
     */
    public function obtenerNotificaciones(Request $request)
    {
        try {
            $usuarioId = $request->input('usuario_id');
            $limite = $request->input('limite', 50);
            $soloNoLeidas = $request->input('solo_no_leidas', false);

            $query = Notificacion::where('usuario_id', $usuarioId)
                                 ->orderBy('fecha_creacion', 'desc');

            if ($soloNoLeidas) {
                $query->noLeidas();
            }

            $notificaciones = $query->limit($limite)->get();

            return response()->json([
                'notificaciones' => $notificaciones,
                'total' => $notificaciones->count(),
                'no_leidas' => Notificacion::where('usuario_id', $usuarioId)->noLeidas()->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener notificaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarComoLeida($id)
    {
        try {
            $notificacion = Notificacion::findOrFail($id);
            $notificacion->marcarComoLeida();

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al marcar notificación',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasComoLeidas(Request $request)
    {
        try {
            $usuarioId = $request->input('usuario_id');

            Notificacion::where('usuario_id', $usuarioId)
                       ->noLeidas()
                       ->update([
                           'leida' => true,
                           'fecha_lectura' => now()
                       ]);

            return response()->json([
                'success' => true,
                'message' => 'Todas las notificaciones marcadas como leídas'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al marcar notificaciones',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar notificación
     */
    public function eliminarNotificacion($id)
    {
        try {
            $notificacion = Notificacion::findOrFail($id);
            $notificacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notificación eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar notificación',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de expedientes observados
     */
    public function estadisticasObservados()
    {
        try {
            $expedientesObservados = Expediente::where('estado', 'OBSERVADO')
                                               ->with(['solicitante', 'asunto'])
                                               ->get();

            $stats = [
                'total' => $expedientesObservados->count(),
                'por_vencer' => 0,
                'vencidos' => 0,
                'dias_promedio' => 0
            ];

            $sumaDias = 0;
            $hoy = now();

            foreach ($expedientesObservados as $exp) {
                $diasTranscurridos = $hoy->diffInDays($exp->fecha_recepcion);
                $sumaDias += $diasTranscurridos;

                if ($diasTranscurridos >= 10) {
                    $stats['vencidos']++;
                } elseif ($diasTranscurridos >= 8) {
                    $stats['por_vencer']++;
                }
            }

            if ($stats['total'] > 0) {
                $stats['dias_promedio'] = round($sumaDias / $stats['total'], 1);
            }

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener estadísticas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de usuarios que recibirán notificaciones
     */
    public function obtenerDestinatarios()
    {
        try {
            $usuarios = DB::table('usuario')
                ->select('id', 'usuario', 'correo', 'rol')
                ->get();

            return response()->json([
                'total' => $usuarios->count(),
                'usuarios' => $usuarios
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener destinatarios',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}