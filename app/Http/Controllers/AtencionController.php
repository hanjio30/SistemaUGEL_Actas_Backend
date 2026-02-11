<?php

namespace App\Http\Controllers;

use App\Models\Atencion;
use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AtencionController extends Controller
{
    /**
     * Registrar una nueva atención
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_expediente' => 'required|exists:expedientes,id_expediente',
            'estado_anterior' => 'required|string|max:50',
            'estado_nuevo' => 'required|in:EN PROCESO,OBSERVADO,LISTO PARA ENTREGA,ENTREGADO',
            'observaciones' => 'nullable|string',
            'usuario' => 'required|string' // Agregar validación del usuario
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos inválidos',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $atencion = Atencion::create([
                'id_expediente' => $request->id_expediente,
                'usuario' => $request->usuario, // Tomar del request en lugar de la sesión
                'estado_anterior' => $request->estado_anterior,
                'estado_nuevo' => $request->estado_nuevo,
                'observaciones' => $request->observaciones,
                'fecha_atencion' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Atención registrada correctamente',
                'data' => $atencion
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al registrar la atención',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el historial de atenciones de un expediente
     */
    public function historial($id_expediente)
    {
        $expediente = Expediente::find($id_expediente);
        
        if (!$expediente) {
            return response()->json(['error' => 'Expediente no encontrado'], 404);
        }

        $atenciones = Atencion::where('id_expediente', $id_expediente)
            ->orderBy('fecha_atencion', 'desc')
            ->get();

        return response()->json($atenciones);
    }

    /**
     * Obtener todas las atenciones con información del expediente
     */
    public function index()
    {
        $atenciones = Atencion::with('expediente.solicitante', 'expediente.asunto')
            ->orderBy('fecha_atencion', 'desc')
            ->get();

        return response()->json($atenciones);
    }

    /**
     * Estadísticas de atenciones por usuario
     */
    public function estadisticasPorUsuario()
    {
        $estadisticas = Atencion::selectRaw('usuario, COUNT(*) as total_atenciones')
            ->groupBy('usuario')
            ->orderBy('total_atenciones', 'desc')
            ->get();

        return response()->json($estadisticas);
    }
}