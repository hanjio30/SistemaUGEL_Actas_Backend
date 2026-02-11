<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * ConsultaController
 * 
 * Controlador específico para consultas públicas de expedientes.
 * No requiere autenticación y está optimizado para ciudadanos.
 */
class ConsultaController extends Controller
{
    /**
     * Consultar expediente por firma de ruta (código de seguimiento)
     * 
     * Endpoint público que permite a los ciudadanos consultar el estado
     * de sus expedientes usando el código de seguimiento único.
     * 
     * @param string $firmaRuta Código de seguimiento del expediente
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarPorFirmaRuta($firmaRuta)
    {
        try {
            // Validar formato del código de seguimiento
            $validator = Validator::make(['firma_ruta' => $firmaRuta], [
                'firma_ruta' => 'required|string|min:10|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El código de seguimiento no tiene un formato válido',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Registrar consulta en logs (para auditoría y estadísticas)
            Log::info('Consulta pública de expediente', [
                'firma_ruta' => $firmaRuta,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
            
            // Buscar expediente con todas sus relaciones
            $expediente = Expediente::with([
                'solicitante', 
                'asunto.documento',
                'historial' => function($query) {
                    $query->orderBy('fecha_cambio', 'desc')->limit(5);
                }
            ])
            ->where('firma_ruta', $firmaRuta)
            ->first();
            
            // Si no se encuentra el expediente
            if (!$expediente) {
                Log::warning('Expediente no encontrado en consulta pública', [
                    'firma_ruta' => $firmaRuta,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún expediente con ese código de seguimiento.',
                    'sugerencias' => [
                        'Verifique que el código esté escrito correctamente',
                        'El código distingue entre mayúsculas y minúsculas',
                        'Puede encontrar el código en el cargo de recepción de su expediente',
                        'Si el problema persiste, comuníquese con nosotros al (043) 321-588'
                    ]
                ], 404);
            }

            // Calcular información adicional
            $diasTranscurridos = Carbon::parse($expediente->fecha_recepcion)->diffInDays(Carbon::now());
            $diasLimite = 10; // Plazo estándar de atención
            $porcentajeProgreso = min(($diasTranscurridos / $diasLimite) * 100, 100);

            // Determinar si está próximo a vencer
            $proximoVencer = $diasTranscurridos >= 8 && $expediente->estado !== 'ENTREGADO';
            $vencido = $diasTranscurridos >= $diasLimite && $expediente->estado !== 'ENTREGADO';

            // Log de consulta exitosa
            Log::info('Expediente encontrado en consulta pública', [
                'firma_ruta' => $firmaRuta,
                'num_expediente' => $expediente->num_expediente,
                'estado' => $expediente->estado,
                'dias_transcurridos' => $diasTranscurridos
            ]);

            // Preparar respuesta estructurada
            $response = [
                'success' => true,
                'message' => 'Expediente encontrado exitosamente',
                'data' => [
                    // Información básica del expediente
                    'id_expediente' => $expediente->id_expediente,
                    'num_expediente' => $expediente->num_expediente,
                    'firma_ruta' => $expediente->firma_ruta,
                    'estado' => $expediente->estado,
                    'fecha_recepcion' => $expediente->fecha_recepcion,
                    'observaciones' => $expediente->observaciones,
                    
                    // Información del solicitante (datos públicos únicamente)
                    'solicitante' => [
                        'nombre_solicitante' => $expediente->solicitante->nombre_solicitante ?? 'No especificado',
                        'tipo_solicitante' => $expediente->solicitante->nombre_tipo ?? 'No especificado'
                    ],
                    
                    // Información del asunto - CORREGIDO PARA INCLUIR TIPO DE DOCUMENTO
                    'asunto' => [
                        'nombre_asunto' => $expediente->asunto->nombre_asunto ?? 'No especificado',
                        'tipo_documento' => $expediente->asunto->documento->nombre_tipo ?? 'No especificado',
                        'documento' => [
                            'nombre_tipo' => $expediente->asunto->documento->nombre_tipo ?? 'No especificado'
                        ]
                    ],
                    
                    // Métricas de tiempo
                    'tiempo' => [
                        'dias_transcurridos' => $diasTranscurridos,
                        'dias_limite' => $diasLimite,
                        'porcentaje_progreso' => round($porcentajeProgreso, 2),
                        'proximo_vencer' => $proximoVencer,
                        'vencido' => $vencido,
                        'fecha_limite_estimada' => Carbon::parse($expediente->fecha_recepcion)
                            ->addDays($diasLimite)
                            ->format('Y-m-d')
                    ],
                    
                    // Historial reciente (últimas 5 actualizaciones)
                    'historial_reciente' => $expediente->historial->map(function($item) {
                        return [
                            'fecha' => $item->fecha_cambio,
                            'estado_anterior' => $item->estado_anterior,
                            'estado_nuevo' => $item->estado_nuevo,
                            'observaciones' => $item->observaciones
                        ];
                    })
                ],
                
                // Información de contacto
                'contacto' => [
                    'institucion' => 'UGEL Santa',
                    'direccion' => 'Jr. Leoncio Prado 242 - Chimbote',
                    'telefono' => '(043) 321-588',
                    'horario' => 'Lunes a Viernes: 8:00 AM - 4:00 PM',
                    'email' => 'mesadepartes@ugelsanta.gob.pe'
                ],
                
                // Metadata
                'metadata' => [
                    'fecha_consulta' => Carbon::now()->toDateTimeString(),
                    'version_api' => '1.0'
                ]
            ];
            
            return response()->json($response, 200);
            
        } catch (\Exception $e) {
            // Log del error
            Log::error('Error en consulta pública de expediente', [
                'firma_ruta' => $firmaRuta,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Respuesta de error genérica (sin exponer detalles técnicos)
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al procesar su consulta. Por favor, intente nuevamente.',
                'error_code' => 'INTERNAL_ERROR',
                'contacto' => [
                    'telefono' => '(043) 321-588',
                    'email' => 'soporte@ugelsanta.gob.pe'
                ]
            ], 500);
        }
    }

    /**
     * Verificar disponibilidad del servicio de consulta
     * 
     * Endpoint para verificar que el servicio está disponible.
     * Útil para health checks y monitoreo.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificarServicio()
    {
        try {
            // Verificar conexión a base de datos
            $dbConnected = \DB::connection()->getPdo() !== null;
            
            return response()->json([
                'success' => true,
                'service' => 'Consulta Pública de Expedientes',
                'status' => 'online',
                'database' => $dbConnected ? 'connected' : 'disconnected',
                'timestamp' => Carbon::now()->toDateTimeString(),
                'version' => '1.0'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'service' => 'Consulta Pública de Expedientes',
                'status' => 'error',
                'message' => 'El servicio no está disponible temporalmente',
                'timestamp' => Carbon::now()->toDateTimeString()
            ], 503);
        }
    }

    /**
     * Obtener estadísticas públicas (opcional)
     * 
     * Endpoint que retorna estadísticas generales sin datos sensibles.
     * Puede ser útil para mostrar métricas en la página principal.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function estadisticasPublicas()
    {
        try {
            $hoy = Carbon::now();
            $inicioMes = Carbon::now()->startOfMonth();
            
            $estadisticas = [
                'success' => true,
                'data' => [
                    'expedientes_mes_actual' => Expediente::whereBetween('fecha_recepcion', [
                        $inicioMes,
                        $hoy
                    ])->count(),
                    
                    'expedientes_entregados_mes' => Expediente::where('estado', 'ENTREGADO')
                        ->whereBetween('fecha_recepcion', [$inicioMes, $hoy])
                        ->count(),
                    
                    'tiempo_promedio_atencion' => $this->calcularTiempoPromedioAtencion(),
                    
                    'estados_actuales' => [
                        'recepcionado' => Expediente::where('estado', 'RECEPCIONADO')->count(),
                        'en_proceso' => Expediente::where('estado', 'EN PROCESO')->count(),
                        'listo_entrega' => Expediente::where('estado', 'LISTO PARA ENTREGA')->count()
                    ]
                ],
                'periodo' => [
                    'inicio' => $inicioMes->format('Y-m-d'),
                    'fin' => $hoy->format('Y-m-d')
                ],
                'ultima_actualizacion' => $hoy->toDateTimeString()
            ];
            
            return response()->json($estadisticas, 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas públicas: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron obtener las estadísticas'
            ], 500);
        }
    }

    /**
     * Calcular tiempo promedio de atención
     * 
     * @return int Días promedio
     */
    private function calcularTiempoPromedioAtencion()
    {
        try {
            $expedientesEntregados = Expediente::where('estado', 'ENTREGADO')
                ->whereMonth('fecha_recepcion', Carbon::now()->month)
                ->get();
            
            if ($expedientesEntregados->isEmpty()) {
                return 0;
            }
            
            $totalDias = $expedientesEntregados->sum(function($expediente) {
                return Carbon::parse($expediente->fecha_recepcion)->diffInDays(Carbon::now());
            });
            
            return round($totalDias / $expedientesEntregados->count());
            
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Buscar expedientes por múltiples criterios (búsqueda avanzada - opcional)
     * 
     * Este método puede ser útil si en el futuro deseas implementar
     * una búsqueda más avanzada para los ciudadanos.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function busquedaAvanzada(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'num_expediente' => 'nullable|string',
                'dni' => 'nullable|string|size:8',
                'codigo_modular' => 'nullable|string',
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de búsqueda inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Expediente::with(['solicitante', 'asunto.documento']);

            // Filtrar por número de expediente
            if ($request->filled('num_expediente')) {
                $query->where('num_expediente', 'LIKE', '%' . $request->num_expediente . '%');
            }

            // Filtrar por DNI del solicitante
            if ($request->filled('dni')) {
                $query->whereHas('solicitante', function($q) use ($request) {
                    $q->where('dni', $request->dni);
                });
            }

            // Filtrar por código modular
            if ($request->filled('codigo_modular')) {
                $query->whereHas('solicitante', function($q) use ($request) {
                    $q->where('codigo_modular', $request->codigo_modular);
                });
            }

            // Filtrar por rango de fechas
            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha_recepcion', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha_recepcion', '<=', $request->fecha_hasta);
            }

            $expedientes = $query->orderBy('fecha_recepcion', 'desc')
                               ->limit(10) // Limitar resultados para no sobrecargar
                               ->get();

            // Log de búsqueda avanzada
            Log::info('Búsqueda avanzada de expedientes', [
                'criterios' => $request->all(),
                'resultados' => $expedientes->count(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'total_resultados' => $expedientes->count(),
                'data' => $expedientes
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en búsqueda avanzada: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al realizar la búsqueda'
            ], 500);
        }
    }
}