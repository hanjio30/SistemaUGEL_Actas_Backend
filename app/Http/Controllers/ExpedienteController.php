<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExpedienteController extends Controller
{
    public function index(Request $request)
    {
        $query = Expediente::with(['solicitante', 'asunto']);
        
        // Filtrar por estado
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }
        
        // Excluir estado OBSERVADO cuando se consulta "Todos"
        if ($request->has('estado_excluir')) {
            $query->where('estado', '!=', $request->estado_excluir);
        }
        
        // Filtrar por rango de fechas
        if ($request->has('fecha_inicio')) {
            $query->whereDate('fecha_recepcion', '>=', $request->fecha_inicio);
        }
        
        if ($request->has('fecha_fin')) {
            $query->whereDate('fecha_recepcion', '<=', $request->fecha_fin);
        }
        
        // Filtrar por solicitante
        if ($request->has('solicitante_id')) {
            $query->where('solicitante_id', $request->solicitante_id);
        }
        
        // Ordenar por fecha de recepción descendente
        $query->orderBy('fecha_recepcion', 'desc');
        
        $expedientes = $query->get();
        return response()->json($expedientes);
    }

    public function show($id)
    {
        $expediente = Expediente::with(['solicitante', 'asunto'])->find($id);
        
        if (!$expediente) {
            return response()->json(['error' => 'Expediente no encontrado'], 404);
        }
        
        return response()->json($expediente);
    }

   public function store(Request $request)
{
    $validated = $request->validate([
        'solicitante_id' => 'required|exists:solicitante,id_solicitante',
        'asunto_id' => 'required|exists:asuntos,id_asunto',
        'fecha_recepcion' => 'required|date',
        'observaciones' => 'nullable|string',
        'usuario' => 'nullable|string'
    ]);

    // Guardar usuario en el request para que el observer lo capture
    if ($request->has('usuario')) {
        request()->merge(['usuario' => $request->usuario]);
    }

    // Generar número de expediente automático
    $year = date('Y');
    $lastExpediente = Expediente::where('num_expediente', 'LIKE', $year . '-%')
                                ->orderBy('id_expediente', 'desc')
                                ->first();
    
    if ($lastExpediente) {
        $lastNumber = intval(explode('-', $lastExpediente->num_expediente)[1]);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    $numExpediente = $year . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    
    // Generar firma de ruta
    $firmaRuta = 'UGEL-' . $year . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(4));
    
    // Crear expediente
    $expediente = Expediente::create([
        'num_expediente' => $numExpediente,
        'firma_ruta' => $firmaRuta,
        'solicitante_id' => $validated['solicitante_id'],
        'asunto_id' => $validated['asunto_id'],
        'fecha_recepcion' => $validated['fecha_recepcion'],
        'estado' => 'RECEPCIONADO',
        'observaciones' => $validated['observaciones'] ?? null
    ]);

    $expediente->load(['solicitante', 'asunto']);
    
    return response()->json($expediente, 201);
}

public function update(Request $request, $id)
{
    $expediente = Expediente::find($id);
    
    if (!$expediente) {
        return response()->json(['error' => 'Expediente no encontrado'], 404);
    }

    $validator = Validator::make($request->all(), [
        'solicitante_id' => 'sometimes|exists:solicitante,id_solicitante',
        'asunto_id' => 'sometimes|exists:asuntos,id_asunto',
        'fecha_recepcion' => 'sometimes|date',
        'estado' => 'sometimes|in:RECEPCIONADO,EN PROCESO,OBSERVADO,LISTO PARA ENTREGA,ENTREGADO',
        'observaciones' => 'nullable|string',
        'usuario' => 'nullable|string'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error' => 'Datos inválidos',
            'messages' => $validator->errors()
        ], 422);
    }

    try {
        // Guardar usuario en el request para que el observer lo capture
        if ($request->has('usuario')) {
            request()->merge(['usuario' => $request->usuario]);
        }

        $expediente->update($request->only([
            'solicitante_id',
            'asunto_id', 
            'fecha_recepcion',
            'estado',
            'observaciones'
        ]));
        
        $expediente->load(['solicitante', 'asunto']);
        
        return response()->json([
            'message' => 'Expediente actualizado correctamente',
            'data' => $expediente
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al actualizar expediente',
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function destroy($id)
    {
        $expediente = Expediente::find($id);
        
        if (!$expediente) {
            return response()->json(['error' => 'Expediente no encontrado'], 404);
        }

        $expediente->delete();
        return response()->json(['message' => 'Expediente eliminado correctamente']);
    }

    public function buscarPorFirmaRuta($firmaRuta)
    {
        $expediente = Expediente::with(['solicitante', 'asunto'])
                                ->where('firma_ruta', $firmaRuta)
                                ->first();
        
        if (!$expediente) {
            return response()->json(['error' => 'Expediente no encontrado'], 404);
        }
        
        return response()->json($expediente);
    }

    /**
     * Exportar expedientes observados a Excel
     */
    public function exportarObservados()
    {
        try {
            // Log para debugging
            Log::info('Iniciando exportación de expedientes observados');

            // Verificar que PhpSpreadsheet esté instalado
            if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                Log::error('PhpSpreadsheet no está instalado');
                return response()->json([
                    'error' => 'La librería PhpSpreadsheet no está instalada',
                    'solucion' => 'Ejecuta: composer require phpoffice/phpspreadsheet'
                ], 500);
            }

            // Obtener expedientes observados
            $expedientes = Expediente::with(['solicitante', 'asunto'])
                ->where('estado', 'OBSERVADO')
                ->orderBy('fecha_recepcion', 'desc')
                ->get();

            Log::info('Expedientes encontrados: ' . $expedientes->count());

            if ($expedientes->isEmpty()) {
                return response()->json([
                    'error' => 'No hay expedientes observados para exportar'
                ], 404);
            }

            // Crear spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Expedientes Observados');

            // CONFIGURAR ENCABEZADOS
            $headers = [
                'A1' => 'N° EXPEDIENTE',
                'B1' => 'FIRMA DE RUTA',
                'C1' => 'SOLICITANTE',
                'D1' => 'DNI / CÓDIGO',
                'E1' => 'TIPO',
                'F1' => 'ASUNTO',
                'G1' => 'FECHA RECEPCIÓN',
                'H1' => 'DÍAS TRANSCURRIDOS',
                'I1' => 'ESTADO',
                'J1' => 'OBSERVACIONES'
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            // Estilo de encabezados
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '083f8f'] // Azul UGEL
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ];

            $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

            // Ajustar altura de encabezado
            $sheet->getRowDimension(1)->setRowHeight(25);

            // LLENAR DATOS
            $row = 2;
            $hoy = new \DateTime();

            foreach ($expedientes as $exp) {
                try {
                    // Calcular días transcurridos
                    $fechaRecepcion = new \DateTime($exp->fecha_recepcion);
                    $diasTranscurridos = $hoy->diff($fechaRecepcion)->days;

                    // DNI o Código Modular - Verificar que solicitante existe
                    $identificacion = 'Sin identificación';
                    if ($exp->solicitante) {
                        $identificacion = $exp->solicitante->dni ?? 
                                         $exp->solicitante->codigo_modular ?? 
                                         'Sin identificación';
                    }

                    $sheet->setCellValue('A' . $row, $exp->num_expediente ?? 'N/A');
                    $sheet->setCellValue('B' . $row, $exp->firma_ruta ?? 'N/A');
                    $sheet->setCellValue('C' . $row, $exp->solicitante->nombre_solicitante ?? 'Sin nombre');
                    $sheet->setCellValue('D' . $row, $identificacion);
                    $sheet->setCellValue('E' . $row, $exp->solicitante->nombre_tipo ?? 'No especificado');
                    $sheet->setCellValue('F' . $row, $exp->asunto->nombre_asunto ?? 'Sin asunto');
                    $sheet->setCellValue('G' . $row, $fechaRecepcion->format('d/m/Y'));
                    $sheet->setCellValue('H' . $row, $diasTranscurridos . '/10 días');
                    $sheet->setCellValue('I' . $row, $exp->estado ?? 'OBSERVADO');
                    $sheet->setCellValue('J' . $row, $exp->observaciones ?? 'Sin observaciones');

                    // Estilo de fila según urgencia
                    $rowStyle = [
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CCCCCC']
                            ]
                        ]
                    ];

                    // Color según días transcurridos
                    if ($diasTranscurridos >= 10) {
                        // Rojo - Vencido
                        $rowStyle['fill'] = [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'fee2e2']
                        ];
                    } elseif ($diasTranscurridos >= 8) {
                        // Amarillo - Advertencia
                        $rowStyle['fill'] = [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'fef3c7']
                        ];
                    }

                    $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray($rowStyle);

                    // Alineación
                    $sheet->getStyle('A' . $row . ':J' . $row)->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle('H' . $row)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $row++;
                } catch (\Exception $e) {
                    Log::error('Error procesando expediente ' . $exp->id_expediente . ': ' . $e->getMessage());
                    // Continuar con el siguiente expediente
                    continue;
                }
            }

            // AJUSTAR ANCHOS DE COLUMNA
            $sheet->getColumnDimension('A')->setWidth(15); // N° Expediente
            $sheet->getColumnDimension('B')->setWidth(25); // Firma Ruta
            $sheet->getColumnDimension('C')->setWidth(30); // Solicitante
            $sheet->getColumnDimension('D')->setWidth(15); // DNI/Código
            $sheet->getColumnDimension('E')->setWidth(12); // Tipo
            $sheet->getColumnDimension('F')->setWidth(30); // Asunto
            $sheet->getColumnDimension('G')->setWidth(15); // Fecha
            $sheet->getColumnDimension('H')->setWidth(18); // Días
            $sheet->getColumnDimension('I')->setWidth(15); // Estado
            $sheet->getColumnDimension('J')->setWidth(40); // Observaciones
            
            // ⚠️ CORRECCIÓN: Aplicar wrap text directamente en el rango de celdas
            $sheet->getStyle('J:J')->getAlignment()->setWrapText(true);

            // AGREGAR INFORMACIÓN ADICIONAL AL FINAL
            $row += 2;
            $sheet->setCellValue('A' . $row, 'RESUMEN:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Total de expedientes observados:');
            $sheet->setCellValue('B' . $row, $expedientes->count());
            
            $row++;
            $proximosVencer = $expedientes->filter(function($exp) use ($hoy) {
                $fechaRecepcion = new \DateTime($exp->fecha_recepcion);
                $dias = $hoy->diff($fechaRecepcion)->days;
                return $dias >= 8;
            })->count();
            
            $sheet->setCellValue('A' . $row, 'Próximos a vencer (8+ días):');
            $sheet->setCellValue('B' . $row, $proximosVencer);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Fecha de generación:');
            $sheet->setCellValue('B' . $row, $hoy->format('d/m/Y H:i:s'));

            // Crear el archivo Excel en memoria
            $writer = new Xlsx($spreadsheet);
            $filename = 'expedientes_observados_' . date('Y-m-d_His') . '.xlsx';
            
            // Crear directorio temporal si no existe
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $filepath = $tempDir . '/' . $filename;

            Log::info('Guardando archivo en: ' . $filepath);

            // Guardar el archivo
            $writer->save($filepath);

            Log::info('Archivo guardado exitosamente');

            // Verificar que el archivo existe
            if (!file_exists($filepath)) {
                throw new \Exception('El archivo no se pudo crear en: ' . $filepath);
            }

            // Retornar el archivo para descarga y eliminarlo después
            return response()->download($filepath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error en exportarObservados: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Error al generar el archivo Excel',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

}