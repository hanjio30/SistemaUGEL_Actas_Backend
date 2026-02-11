<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Atencion;
use App\Models\Entrega;
use App\Models\Asunto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ReporteController extends Controller
{
    /**
     * Reporte de expedientes por período
     * GET /api/reportes/expedientes-periodo
     */
    public function expedientesPorPeriodo(Request $request)
    {
        try {
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'documento_id' => 'nullable|integer',
                'estado' => 'nullable|string'
            ]);

            $query = Expediente::with(['solicitante', 'asunto.documento'])
                ->whereBetween('fecha_recepcion', [
                    $request->fecha_inicio, 
                    $request->fecha_fin
                ]);

            // Filtro por tipo de documento
            if ($request->has('documento_id') && $request->documento_id) {
                $query->whereHas('asunto', function($q) use ($request) {
                    $q->where('documento_id', $request->documento_id);
                });
            }

            // Filtro por estado
            if ($request->has('estado') && $request->estado && $request->estado !== 'Todos') {
                $query->where('estado', $request->estado);
            }

            $expedientes = $query->orderBy('fecha_recepcion', 'desc')->get();

            // Calcular estadísticas
            $totalRecibidos = $expedientes->count();
            $totalAtendidos = $expedientes->whereIn('estado', ['LISTO PARA ENTREGA', 'ENTREGADO'])->count();
            $totalObservados = $expedientes->where('estado', 'OBSERVADO')->count();
            $totalEnProceso = $expedientes->where('estado', 'EN PROCESO')->count();

            // Calcular tiempo promedio de atención
            $expedientesEntregados = $expedientes->where('estado', 'ENTREGADO');
            $tiempoPromedio = 0;
            $dentroDelPlazo = 0;

            if ($expedientesEntregados->count() > 0) {
                $totalDias = 0;
                $contadorConEntrega = 0;
                
                foreach ($expedientesEntregados as $exp) {
                    // Verificar si el expediente tiene entregas
                    if ($exp->relationLoaded('entregas')) {
                        $entrega = $exp->entregas->first();
                    } else {
                        $entrega = $exp->entregas()->first();
                    }
                    
                    if ($entrega && isset($entrega->dias_atencion)) {
                        $totalDias += $entrega->dias_atencion;
                        $contadorConEntrega++;
                        if ($entrega->dias_atencion <= 10) {
                            $dentroDelPlazo++;
                        }
                    } else {
                        // Si no hay entrega, calcular días desde la recepción
                        $fechaRecepcion = new \DateTime($exp->fecha_recepcion);
                        $hoy = new \DateTime();
                        $dias = $hoy->diff($fechaRecepcion)->days;
                        $totalDias += $dias;
                        $contadorConEntrega++;
                        if ($dias <= 10) {
                            $dentroDelPlazo++;
                        }
                    }
                }
                
                if ($contadorConEntrega > 0) {
                    $tiempoPromedio = round($totalDias / $contadorConEntrega, 1);
                }
            }

            $porcentajeDentroDelPlazo = $expedientesEntregados->count() > 0 
                ? round(($dentroDelPlazo / $expedientesEntregados->count()) * 100) 
                : 0;

            // Top 5 asuntos más solicitados
            $asuntosMasSolicitados = $expedientes->groupBy('asunto_id')
                ->map(function($items) {
                    $primerItem = $items->first();
                    return [
                        'asunto' => $primerItem && $primerItem->asunto ? $primerItem->asunto->nombre_asunto : 'Sin asunto',
                        'cantidad' => $items->count(),
                        'porcentaje' => 0 // Se calculará después
                    ];
                })
                ->sortByDesc('cantidad')
                ->take(5)
                ->values();

            // Calcular porcentajes
            if ($totalRecibidos > 0) {
                $asuntosMasSolicitados = $asuntosMasSolicitados->map(function($item) use ($totalRecibidos) {
                    $item['porcentaje'] = round(($item['cantidad'] / $totalRecibidos) * 100);
                    return $item;
                });
            }

            // Estadísticas por tipo de documento
            $porTipoDocumento = $expedientes->groupBy(function($exp) {
                return ($exp->asunto && $exp->asunto->documento) ? $exp->asunto->documento->nombre_tipo : 'Sin tipo';
            })->map->count();

            return response()->json([
                'resumen' => [
                    'total_recibidos' => $totalRecibidos,
                    'total_atendidos' => $totalAtendidos,
                    'total_observados' => $totalObservados,
                    'total_en_proceso' => $totalEnProceso,
                    'tiempo_promedio_dias' => $tiempoPromedio,
                    'porcentaje_dentro_plazo' => $porcentajeDentroDelPlazo
                ],
                'top_asuntos' => $asuntosMasSolicitados,
                'por_tipo_documento' => $porTipoDocumento,
                'expedientes' => $expedientes
            ]);

        } catch (\Exception $e) {
            Log::error('Error en expedientesPorPeriodo: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Error al generar reporte',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Reporte de estados actuales
     * GET /api/reportes/estados-actuales
     */
    public function estadosActuales()
    {
        try {
            $estados = [
                'RECEPCIONADO',
                'EN PROCESO',
                'OBSERVADO',
                'LISTO PARA ENTREGA',
                'ENTREGADO'
            ];

            $estadisticas = [];
            $total = Expediente::count();

            foreach ($estados as $estado) {
                $cantidad = Expediente::where('estado', $estado)->count();
                $porcentaje = $total > 0 ? round(($cantidad / $total) * 100, 1) : 0;

                $estadisticas[] = [
                    'estado' => $estado,
                    'cantidad' => $cantidad,
                    'porcentaje' => $porcentaje
                ];
            }

            // Expedientes con más días en el sistema
            $expedientesPendientes = Expediente::with(['solicitante', 'asunto'])
                ->whereNotIn('estado', ['ENTREGADO'])
                ->get()
                ->map(function($exp) {
                    // Calcular días transcurridos desde la recepción hasta hoy
                    $fechaRecepcion = new \DateTime($exp->fecha_recepcion);
                    $hoy = new \DateTime();
                    $dias = $hoy->diff($fechaRecepcion)->days; // Siempre positivo
                    
                    return [
                        'id' => $exp->id_expediente,
                        'num_expediente' => $exp->num_expediente,
                        'solicitante' => $exp->solicitante ? $exp->solicitante->nombre_solicitante : 'N/A',
                        'asunto' => $exp->asunto ? $exp->asunto->nombre_asunto : 'N/A',
                        'estado' => $exp->estado,
                        'dias_transcurridos' => $dias,
                        'fecha_recepcion' => $exp->fecha_recepcion
                    ];
                })
                ->sortByDesc('dias_transcurridos')
                ->take(10)
                ->values();

            return response()->json([
                'por_estado' => $estadisticas,
                'total_expedientes' => $total,
                'expedientes_mas_antiguos' => $expedientesPendientes
            ]);

        } catch (\Exception $e) {
            Log::error('Error en estadosActuales: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener estados',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte por colaborador (SOLO COLABORADORES - CORREGIDO)
     * GET /api/reportes/por-colaborador
     */
    public function porColaborador(Request $request)
    {
        try {
            $request->validate([
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
                'usuario' => 'nullable|string'
            ]);

            // ✅ CORRECCIÓN FINAL: Obtener SOLO usuarios con rol "Colaborador"
            // y crear un mapeo de usuario -> datos completos
            $todosLosColaboradores = DB::table('usuario')
                ->select('usuario as nombre_usuario', 'nombre_completo', 'rol')
                ->where('estado', 'Activo')
                ->where('rol', 'Colaborador')
                ->get()
                ->keyBy('nombre_usuario');

            // Obtener lista de nombres de usuarios colaboradores para filtrar
            $nombresColaboradores = $todosLosColaboradores->keys()->toArray();

            $query = Atencion::query();

            // ✅ FILTRO CRÍTICO: Solo atenciones de colaboradores
            $query->whereIn('usuario', $nombresColaboradores);

            // Filtrar por rango de fechas
            if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
                $query->whereBetween('fecha_atencion', [
                    $request->fecha_inicio,
                    $request->fecha_fin
                ]);
            }

            // Filtrar por usuario específico
            if ($request->has('usuario') && $request->usuario) {
                $query->where('usuario', $request->usuario);
            }

            $atenciones = $query->with('expediente')->get();

            // Agrupar por usuario
            $atencionesPorUsuario = $atenciones->groupBy('usuario');

            // Crear estadísticas SOLO para colaboradores
            $estadisticasPorUsuario = [];

            foreach ($todosLosColaboradores as $nombreUsuario => $usuario) {
                $atencionesUsuario = $atencionesPorUsuario->get($nombreUsuario, collect());
                
                $total = $atencionesUsuario->count();
                $enProceso = $atencionesUsuario->where('estado_nuevo', 'EN PROCESO')->count();
                $observados = $atencionesUsuario->where('estado_nuevo', 'OBSERVADO')->count();
                $listos = $atencionesUsuario->where('estado_nuevo', 'LISTO PARA ENTREGA')->count();

                $estadisticasPorUsuario[] = [
                    'usuario' => $nombreUsuario,
                    'nombre_completo' => $usuario->nombre_completo,
                    'rol' => $usuario->rol,
                    'total_atenciones' => $total,
                    'en_proceso' => $enProceso,
                    'observados' => $observados,
                    'listos_entrega' => $listos,
                    'promedio_diario' => 0
                ];
            }

            // Ordenar por total de atenciones descendente
            usort($estadisticasPorUsuario, function($a, $b) {
                return $b['total_atenciones'] - $a['total_atenciones'];
            });

            return response()->json([
                'estadisticas' => $estadisticasPorUsuario,
                'total_atenciones' => $atenciones->count(),
                'total_usuarios' => count($estadisticasPorUsuario)
            ]);

        } catch (\Exception $e) {
            Log::error('Error en porColaborador: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Error al generar reporte',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de tiempos de atención
     * GET /api/reportes/tiempos-atencion
     */
    public function tiemposAtencion(Request $request)
    {
        try {
            $request->validate([
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio'
            ]);

            $query = Entrega::with(['expediente.asunto']);

            // Filtrar por rango de fechas
            if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
                $query->whereBetween('fecha_entrega', [
                    $request->fecha_inicio,
                    $request->fecha_fin
                ]);
            }

            $entregas = $query->get();

            // Agrupar por asunto
            $tiemposPorAsunto = $entregas->groupBy(function($entrega) {
                return $entrega->expediente->asunto->nombre_asunto ?? 'Sin asunto';
            })->map(function($items, $asunto) {
                $dias = $items->pluck('dias_atencion');
                $dentroDelPlazo = $items->where('dias_atencion', '<=', 10)->count();
                $total = $items->count();

                return [
                    'asunto' => $asunto,
                    'promedio_dias' => round($dias->avg(), 1),
                    'minimo_dias' => $dias->min(),
                    'maximo_dias' => $dias->max(),
                    'total_entregas' => $total,
                    'dentro_plazo' => $dentroDelPlazo,
                    'porcentaje_dentro_plazo' => $total > 0 ? round(($dentroDelPlazo / $total) * 100) : 0
                ];
            })->sortByDesc('total_entregas')->values();

            // Identificar cuellos de botella
            $cuellosBottella = $tiemposPorAsunto->filter(function($item) {
                return $item['porcentaje_dentro_plazo'] < 80;
            })->values();

            return response()->json([
                'tiempos_por_asunto' => $tiemposPorAsunto,
                'cuellos_botella' => $cuellosBottella,
                'promedio_general' => round($entregas->avg('dias_atencion'), 1),
                'total_entregas' => $entregas->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error en tiemposAtencion: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al calcular tiempos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de expedientes observados
     * GET /api/reportes/expedientes-observados
     */
    public function expedientesObservados()
    {
        try {
            $observados = Expediente::with(['solicitante', 'asunto'])
                ->where('estado', 'OBSERVADO')
                ->get()
                ->map(function($exp) {
                    // Calcular días transcurridos correctamente
                    $fechaRecepcion = new \DateTime($exp->fecha_recepcion);
                    $hoy = new \DateTime();
                    $dias = $hoy->diff($fechaRecepcion)->days; // Siempre positivo
                    
                    return [
                        'id' => $exp->id_expediente,
                        'num_expediente' => $exp->num_expediente,
                        'firma_ruta' => $exp->firma_ruta,
                        'solicitante' => $exp->solicitante ? $exp->solicitante->nombre_solicitante : 'N/A',
                        'dni_codigo' => $exp->solicitante ? ($exp->solicitante->dni ?? $exp->solicitante->codigo_modular ?? 'N/A') : 'N/A',
                        'tipo_solicitante' => $exp->solicitante ? ($exp->solicitante->nombre_tipo ?? 'N/A') : 'N/A',
                        'asunto' => $exp->asunto ? $exp->asunto->nombre_asunto : 'N/A',
                        'fecha_recepcion' => $exp->fecha_recepcion,
                        'dias_transcurridos' => $dias,
                        'observaciones' => $exp->observaciones,
                        'urgencia' => $dias >= 10 ? 'alta' : ($dias >= 8 ? 'media' : 'normal')
                    ];
                })
                ->sortByDesc('dias_transcurridos')
                ->values();

            $estadisticas = [
                'total' => $observados->count(),
                'urgencia_alta' => $observados->where('urgencia', 'alta')->count(),
                'urgencia_media' => $observados->where('urgencia', 'media')->count(),
                'promedio_dias' => $observados->count() > 0 ? round($observados->avg('dias_transcurridos'), 1) : 0
            ];

            return response()->json([
                'expedientes' => $observados,
                'estadisticas' => $estadisticas
            ]);

        } catch (\Exception $e) {
            Log::error('Error en expedientesObservados: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener observados',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de entregas (CORREGIDO)
     * GET /api/reportes/entregas
     */
    public function reporteEntregas(Request $request)
    {
        try {
            $request->validate([
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio'
            ]);

            $query = Entrega::with(['expediente.solicitante', 'expediente.asunto']);

            if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
                $query->whereBetween('fecha_entrega', [
                    $request->fecha_inicio,
                    $request->fecha_fin
                ]);
            }

            $entregas = $query->orderBy('fecha_entrega', 'desc')->get();

            $estadisticas = [
                'total_entregas' => $entregas->count(),
                'entrega_titular' => $entregas->where('tipo_recogida', 'titular')->count(),
                'entrega_tercero' => $entregas->where('tipo_recogida', 'tercero')->count(),
                'tiempo_promedio' => round($entregas->avg('dias_atencion'), 1) ?: 0,
                'tiempo_minimo' => $entregas->min('dias_atencion') ?: 0,
                'tiempo_maximo' => $entregas->max('dias_atencion') ?: 0
            ];

            // Entregas por día
            $entregasPorDia = $entregas->groupBy(function($entrega) {
                return $entrega->fecha_entrega ? $entrega->fecha_entrega->format('Y-m-d') : 'Sin fecha';
            })->map->count();

            // Entregas por funcionario
            $entregasPorFuncionario = $entregas->groupBy('entregado_por')
                ->map->count()
                ->sortDesc();

            return response()->json([
                'estadisticas' => $estadisticas,
                'entregas' => $entregas->toArray(),
                'por_dia' => $entregasPorDia,
                'por_funcionario' => $entregasPorFuncionario
            ]);

        } catch (\Exception $e) {
            Log::error('Error en reporteEntregas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar reporte',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar reporte a Excel
     * POST /api/reportes/exportar-excel
     */
    public function exportarExcel(Request $request)
    {
        try {
            $request->validate([
                'tipo' => 'required|in:expedientes,observados,entregas,colaboradores',
                'datos' => 'required|array'
            ]);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            switch ($request->tipo) {
                case 'expedientes':
                    $this->generarExcelExpedientes($sheet, $request->datos);
                    $filename = 'reporte_expedientes_' . date('Y-m-d_His') . '.xlsx';
                    break;
                
                case 'observados':
                    $this->generarExcelObservados($sheet, $request->datos);
                    $filename = 'expedientes_observados_' . date('Y-m-d_His') . '.xlsx';
                    break;
                
                case 'entregas':
                    $this->generarExcelEntregas($sheet, $request->datos);
                    $filename = 'reporte_entregas_' . date('Y-m-d_His') . '.xlsx';
                    break;
                
                case 'colaboradores':
                    $this->generarExcelColaboradores($sheet, $request->datos);
                    $filename = 'reporte_colaboradores_' . date('Y-m-d_His') . '.xlsx';
                    break;
            }

            $writer = new Xlsx($spreadsheet);
            $tempDir = storage_path('app/temp');
            
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $filepath = $tempDir . '/' . $filename;
            $writer->save($filepath);

            return response()->download($filepath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error en exportarExcel: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar Excel',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function generarExcelExpedientes($sheet, $datos)
    {
        $sheet->setTitle('Reporte de Expedientes');
        
        $headers = ['N° Expediente', 'Solicitante', 'Asunto', 'Estado', 'Fecha Recepción', 'Días Transcurridos'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $this->aplicarEstiloEncabezado($sheet, 'A1:F1');

        $row = 2;
        foreach ($datos as $item) {
            // ✅ CORRECCIÓN: Calcular días transcurridos correctamente
            $diasTranscurridos = 0;
            if (isset($item['fecha_recepcion'])) {
                $fechaRecepcion = new \DateTime($item['fecha_recepcion']);
                $hoy = new \DateTime();
                $diasTranscurridos = (int) $hoy->diff($fechaRecepcion)->days;
            }
            
            $sheet->setCellValue('A' . $row, $item['num_expediente'] ?? '');
            $sheet->setCellValue('B' . $row, $item['solicitante']['nombre_solicitante'] ?? '');
            $sheet->setCellValue('C' . $row, $item['asunto']['nombre_asunto'] ?? '');
            $sheet->setCellValue('D' . $row, $item['estado'] ?? '');
            $sheet->setCellValue('E' . $row, $item['fecha_recepcion'] ?? '');
            $sheet->setCellValue('F' . $row, $diasTranscurridos);
            $row++;
        }

        $this->ajustarAnchos($sheet, ['A' => 15, 'B' => 30, 'C' => 30, 'D' => 20, 'E' => 15, 'F' => 15]);
    }

    private function generarExcelObservados($sheet, $datos)
    {
        $sheet->setTitle('Expedientes Observados');
        
        $headers = ['N° Expediente', 'Firma Ruta', 'Solicitante', 'Asunto', 'Días', 'Observaciones'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $this->aplicarEstiloEncabezado($sheet, 'A1:F1');

        $row = 2;
        foreach ($datos as $item) {
            $sheet->setCellValue('A' . $row, $item['num_expediente'] ?? '');
            $sheet->setCellValue('B' . $row, $item['firma_ruta'] ?? '');
            $sheet->setCellValue('C' . $row, $item['solicitante'] ?? '');
            $sheet->setCellValue('D' . $row, $item['asunto'] ?? '');
            $sheet->setCellValue('E' . $row, $item['dias_transcurridos'] ?? 0);
            $sheet->setCellValue('F' . $row, $item['observaciones'] ?? '');
            $row++;
        }

        $this->ajustarAnchos($sheet, ['A' => 15, 'B' => 25, 'C' => 30, 'D' => 30, 'E' => 10, 'F' => 40]);
    }

    private function generarExcelEntregas($sheet, $datos)
    {
        $sheet->setTitle('Reporte de Entregas');
        
        $headers = ['N° Expediente', 'Solicitante', 'Tipo Recogida', 'Fecha Entrega', 'Días Atención', 'Entregado Por'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $this->aplicarEstiloEncabezado($sheet, 'A1:F1');

        $row = 2;
        foreach ($datos as $item) {
            $sheet->setCellValue('A' . $row, $item['expediente']['num_expediente'] ?? '');
            $sheet->setCellValue('B' . $row, $item['expediente']['solicitante']['nombre_solicitante'] ?? '');
            $sheet->setCellValue('C' . $row, strtoupper($item['tipo_recogida'] ?? ''));
            $sheet->setCellValue('D' . $row, $item['fecha_entrega'] ?? '');
            $sheet->setCellValue('E' . $row, $item['dias_atencion'] ?? 0);
            $sheet->setCellValue('F' . $row, $item['entregado_por'] ?? '');
            $row++;
        }

        $this->ajustarAnchos($sheet, ['A' => 15, 'B' => 30, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 25]);
    }

    private function generarExcelColaboradores($sheet, $datos)
    {
        $sheet->setTitle('Reporte por Colaborador');
        
        $headers = ['Usuario', 'Nombre Completo', 'Total Atenciones', 'En Proceso', 'Observados', 'Listos Entrega'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $this->aplicarEstiloEncabezado($sheet, 'A1:F1');

        $row = 2;
        foreach ($datos as $item) {
            $sheet->setCellValue('A' . $row, $item['usuario'] ?? '');
            $sheet->setCellValue('B' . $row, $item['nombre_completo'] ?? '');
            $sheet->setCellValue('C' . $row, $item['total_atenciones'] ?? 0);
            $sheet->setCellValue('D' . $row, $item['en_proceso'] ?? 0);
            $sheet->setCellValue('E' . $row, $item['observados'] ?? 0);
            $sheet->setCellValue('F' . $row, $item['listos_entrega'] ?? 0);
            $row++;
        }

        $this->ajustarAnchos($sheet, ['A' => 25, 'B' => 30, 'C' => 18, 'D' => 15, 'E' => 15, 'F' => 18]);
    }

    private function aplicarEstiloEncabezado($sheet, $range)
    {
        $style = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '083f8f']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ];

        $sheet->getStyle($range)->applyFromArray($style);
        $sheet->getRowDimension('1')->setRowHeight(25);
    }

    private function ajustarAnchos($sheet, $anchos)
    {
        foreach ($anchos as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }
}