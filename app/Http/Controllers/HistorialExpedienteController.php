<?php

namespace App\Http\Controllers;

use App\Models\HistorialExpediente;
use App\Models\Expediente;
use Illuminate\Http\Request;

class HistorialExpedienteController extends Controller
{
    /**
     * Obtener historial de un expediente específico
     * GET /api/expedientes/{expediente_id}/historial
     */
    public function index($expediente_id)
    {
        try {
            // Buscar el expediente
            $expediente = Expediente::with(['solicitante', 'asunto'])
                                    ->find($expediente_id);
            
            if (!$expediente) {
                return response()->json([
                    'error' => 'Expediente no encontrado'
                ], 404);
            }
            
            // Obtener el historial ordenado por fecha descendente
            $historial = HistorialExpediente::where('expediente_id', $expediente_id)
                                            ->orderBy('fecha_cambio', 'desc')
                                            ->get();
            
            return response()->json([
                'expediente' => $expediente,
                'historial' => $historial
            ]);
            
        } catch (\Exception $e) {
            // Log del error para debugging
            \Log::error('Error en HistorialExpedienteController@index: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al cargar el historial',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Agregar entrada manual al historial
     * POST /api/historial
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'expediente_id' => 'required|exists:expedientes,id_expediente',
                'usuario' => 'required|string|max:255',
                'estado_nuevo' => 'required|string|max:50',
                'estado_anterior' => 'nullable|string|max:50',
                'observaciones' => 'nullable|string'
            ]);
            
            $historial = HistorialExpediente::create($validated);
            
            return response()->json($historial, 201);
            
        } catch (\Exception $e) {
            \Log::error('Error en HistorialExpedienteController@store: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al crear el registro de historial',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}