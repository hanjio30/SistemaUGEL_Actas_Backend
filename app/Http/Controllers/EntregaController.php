<?php

namespace App\Http\Controllers;

use App\Models\Entrega;
use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class EntregaController extends Controller
{
    /**
     * Listar todas las entregas con filtros
     */
    public function index(Request $request)
    {
        $query = Entrega::with(['expediente.solicitante', 'expediente.asunto']);
        
        // Filtrar por rango de fechas
        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $query->fechaEntre($request->fecha_inicio, $request->fecha_fin);
        }
        
        // Filtrar por tipo de recogida
        if ($request->has('tipo_recogida')) {
            $query->tipoRecogida($request->tipo_recogida);
        }
        
        // Filtrar por DNI
        if ($request->has('dni')) {
            $query->where(function($q) use ($request) {
                $q->where('dni_recoge', $request->dni)
                  ->orWhere('dni_autorizado', $request->dni);
            });
        }
        
        // Ordenar por fecha de entrega descendente
        $query->orderBy('fecha_entrega', 'desc');
        
        $entregas = $query->get();
        return response()->json($entregas);
    }

    /**
     * Mostrar una entrega específica
     */
    public function show($id)
    {
        $entrega = Entrega::with(['expediente.solicitante', 'expediente.asunto'])->find($id);
        
        if (!$entrega) {
            return response()->json(['error' => 'Entrega no encontrada'], 404);
        }
        
        return response()->json($entrega);
    }

    /**
     * Registrar una nueva entrega
     */
    public function store(Request $request)
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'expediente_id' => 'required|exists:expedientes,id_expediente',
            'dni_recoge' => 'required|string|size:8',
            'tipo_recogida' => 'required|in:titular,tercero',
            'nombre_autorizado' => 'required_if:tipo_recogida,tercero|string|max:255',
            'dni_autorizado' => 'required_if:tipo_recogida,tercero|string|size:8',
            'documento_autorizacion' => 'nullable|file|mimes:pdf|max:5120', // 5MB máximo
            'observaciones' => 'nullable|string',
            'entregado_por' => 'nullable|string|max:255'
        ], [
            'expediente_id.required' => 'El expediente es obligatorio',
            'expediente_id.exists' => 'El expediente no existe',
            'dni_recoge.required' => 'El DNI de quien recoge es obligatorio',
            'dni_recoge.size' => 'El DNI debe tener 8 dígitos',
            'tipo_recogida.required' => 'El tipo de recogida es obligatorio',
            'tipo_recogida.in' => 'El tipo de recogida debe ser "titular" o "tercero"',
            'nombre_autorizado.required_if' => 'El nombre del autorizado es obligatorio cuando recoge un tercero',
            'dni_autorizado.required_if' => 'El DNI del autorizado es obligatorio cuando recoge un tercero',
            'documento_autorizacion.mimes' => 'El documento debe ser un archivo PDF',
            'documento_autorizacion.max' => 'El documento no debe superar los 5MB'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Verificar que el expediente existe y está listo para entrega
            $expediente = Expediente::find($request->expediente_id);
            
            if (!$expediente) {
                return response()->json([
                    'error' => 'Expediente no encontrado'
                ], 404);
            }

            if ($expediente->estado !== 'LISTO PARA ENTREGA') {
                return response()->json([
                    'error' => 'El expediente no está listo para entrega',
                    'estado_actual' => $expediente->estado
                ], 400);
            }

            // Calcular días de atención
            $fechaRecepcion = new \DateTime($expediente->fecha_recepcion);
            $fechaEntrega = new \DateTime();
            $diasAtencion = $fechaEntrega->diff($fechaRecepcion)->days;

            // Manejar documento de autorización si existe
            $rutaDocumento = null;
            if ($request->hasFile('documento_autorizacion')) {
                $file = $request->file('documento_autorizacion');
                $nombreArchivo = 'autorizacion_' . $expediente->num_expediente . '_' . time() . '.pdf';
                $rutaDocumento = $file->storeAs('autorizaciones', $nombreArchivo, 'public');
            }

            // Obtener el nombre del funcionario que entrega
            // Prioridad: 1) Request, 2) Usuario autenticado, 3) Por defecto
            $entregadoPor = $request->entregado_por;
            
            if (empty($entregadoPor) && Auth::check()) {
                $usuario = Auth::user();
                $entregadoPor = $usuario->name ?? $usuario->nombre ?? null;
            }
            
            if (empty($entregadoPor)) {
                $entregadoPor = 'Funcionario UGEL';
            }

            // Crear registro de entrega
            $entrega = Entrega::create([
                'expediente_id' => $request->expediente_id,
                'dni_recoge' => $request->dni_recoge,
                'tipo_recogida' => $request->tipo_recogida,
                'nombre_autorizado' => $request->tipo_recogida === 'tercero' ? $request->nombre_autorizado : null,
                'dni_autorizado' => $request->tipo_recogida === 'tercero' ? $request->dni_autorizado : null,
                'documento_autorizacion' => $rutaDocumento,
                'observaciones' => $request->observaciones,
                'fecha_entrega' => $fechaEntrega,
                'hora_entrega' => $fechaEntrega->format('H:i:s'),
                'dias_atencion' => $diasAtencion,
                'entregado_por' => $entregadoPor
            ]);

            // Actualizar estado del expediente a ENTREGADO
            $observacionesActualizadas = $expediente->observaciones ?? '';
            $observacionesActualizadas .= "\n--- ENTREGA REGISTRADA ---\n";
            $observacionesActualizadas .= "Fecha: " . $fechaEntrega->format('d/m/Y H:i:s') . "\n";
            $observacionesActualizadas .= "Tipo: " . strtoupper($request->tipo_recogida) . "\n";
            $observacionesActualizadas .= "DNI: " . ($request->tipo_recogida === 'tercero' ? $request->dni_autorizado : $request->dni_recoge) . "\n";
            $observacionesActualizadas .= "Entregado por: " . $entregadoPor . "\n";
            
            if ($request->observaciones) {
                $observacionesActualizadas .= "Observaciones: " . $request->observaciones . "\n";
            }

            $expediente->update([
                'estado' => 'ENTREGADO',
                'observaciones' => trim($observacionesActualizadas)
            ]);

            DB::commit();

            // Cargar relaciones para la respuesta
            $entrega->load(['expediente.solicitante', 'expediente.asunto']);

            return response()->json([
                'message' => 'Entrega registrada correctamente',
                'data' => $entrega
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al registrar entrega: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al registrar la entrega',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener entregas por expediente
     */
    public function porExpediente($expedienteId)
    {
        $entregas = Entrega::with(['expediente.solicitante', 'expediente.asunto'])
            ->where('expediente_id', $expedienteId)
            ->orderBy('fecha_entrega', 'desc')
            ->get();
        
        return response()->json($entregas);
    }

    /**
     * Estadísticas de entregas
     */
    public function estadisticas(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth());
        $fechaFin = $request->input('fecha_fin', now()->endOfMonth());

        $entregas = Entrega::fechaEntre($fechaInicio, $fechaFin)->get();

        $estadisticas = [
            'total_entregas' => $entregas->count(),
            'entregas_titular' => $entregas->where('tipo_recogida', 'titular')->count(),
            'entregas_tercero' => $entregas->where('tipo_recogida', 'tercero')->count(),
            'tiempo_promedio_atencion' => round($entregas->avg('dias_atencion'), 2),
            'tiempo_minimo_atencion' => $entregas->min('dias_atencion'),
            'tiempo_maximo_atencion' => $entregas->max('dias_atencion'),
            'entregas_por_dia' => $entregas->groupBy(function($item) {
                return $item->fecha_entrega->format('Y-m-d');
            })->map->count(),
            'entregas_por_funcionario' => $entregas->groupBy('entregado_por')->map->count(),
        ];

        return response()->json($estadisticas);
    }

    /**
     * Descargar documento de autorización
     */
    public function descargarAutorizacion($id)
    {
        $entrega = Entrega::find($id);
        
        if (!$entrega) {
            return response()->json(['error' => 'Entrega no encontrada'], 404);
        }

        if (!$entrega->documento_autorizacion) {
            return response()->json(['error' => 'No hay documento de autorización'], 404);
        }

        $path = storage_path('app/public/' . $entrega->documento_autorizacion);

        if (!file_exists($path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        return response()->download($path);
    }

    /**
     * Eliminar una entrega (solo para correcciones administrativas)
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $entrega = Entrega::find($id);
            
            if (!$entrega) {
                return response()->json(['error' => 'Entrega no encontrada'], 404);
            }

            // Eliminar documento de autorización si existe
            if ($entrega->documento_autorizacion) {
                Storage::disk('public')->delete($entrega->documento_autorizacion);
            }

            // Revertir estado del expediente
            $expediente = $entrega->expediente;
            if ($expediente) {
                $expediente->update(['estado' => 'LISTO PARA ENTREGA']);
            }

            $entrega->delete();

            DB::commit();

            return response()->json([
                'message' => 'Entrega eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al eliminar entrega: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al eliminar la entrega',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}