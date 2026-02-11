<?php
// app/Http/Controllers/AsuntoController.php
namespace App\Http\Controllers;

use App\Models\Asunto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class AsuntoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Asunto::with('documento');
            
            if ($request->has('documento_id')) {
                $query->where('documento_id', $request->documento_id);
            }
            
            if ($request->has('activo')) {
                $query->where('activo', $request->activo);
            }
            
            $asuntos = $query->orderBy('nombre_asunto', 'asc')->get();
            return response()->json($asuntos);
        } catch (Exception $e) {
            Log::error('Error en AsuntoController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al obtener asuntos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Log detallado para debugging
            Log::info('=== INICIO CREACIÓN ASUNTO ===');
            Log::info('Request completo:', $request->all());
            Log::info('Headers:', $request->headers->all());
            
            // Validación
            $validated = $request->validate([
                'nombre_asunto' => 'required|string|max:255',
                'documento_id' => 'required|integer|exists:documento,id_documento',
                'activo' => 'sometimes|boolean'
            ]);
            
            // Asegurar valor por defecto para activo
            if (!isset($validated['activo'])) {
                $validated['activo'] = true;
            }
            
            Log::info('Datos validados:', $validated);
            
            // Verificar que el documento existe
            $documentoExiste = DB::table('documento')
                ->where('id_documento', $validated['documento_id'])
                ->exists();
            
            if (!$documentoExiste) {
                Log::error('Documento no existe', ['documento_id' => $validated['documento_id']]);
                return response()->json([
                    'error' => 'El documento especificado no existe'
                ], 422);
            }
            
            Log::info('Documento verificado, procediendo a crear asunto...');
            
            // Intentar crear el asunto
            DB::beginTransaction();
            
            try {
                $asunto = Asunto::create($validated);
                
                Log::info('Asunto creado:', [
                    'id' => $asunto->id_asunto,
                    'nombre' => $asunto->nombre_asunto
                ]);
                
                // Cargar la relación
                $asunto->load('documento');
                
                DB::commit();
                
                Log::info('=== CREACIÓN EXITOSA ===');
                
                return response()->json($asunto, 201);
                
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Error de base de datos', [
                'message' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? []
            ]);
            
            return response()->json([
                'error' => 'Error de base de datos',
                'message' => 'No se pudo guardar el asunto. Verifica que la tabla y columnas existan.',
                'details' => config('app.debug') ? $e->getMessage() : null,
                'sql' => config('app.debug') ? $e->getSql() : null
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Error general en store', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al crear asunto',
                'message' => $e->getMessage(),
                'file' => config('app.debug') ? $e->getFile() : null,
                'line' => config('app.debug') ? $e->getLine() : null
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $asunto = Asunto::find($id);
            
            if (!$asunto) {
                return response()->json(['error' => 'Asunto no encontrado'], 404);
            }

            Log::info("Actualizando asunto ID $id:", $request->all());

            $validated = $request->validate([
                'nombre_asunto' => 'sometimes|string|max:255',
                'documento_id' => 'sometimes|integer|exists:documento,id_documento',
                'activo' => 'sometimes|boolean'
            ]);

            $asunto->update($validated);
            $asunto->load('documento');
            
            Log::info("Asunto actualizado exitosamente: ID $id");
            
            return response()->json($asunto);
            
        } catch (Exception $e) {
            Log::error('Error en update', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Error al actualizar asunto',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $asunto = Asunto::find($id);
            
            if (!$asunto) {
                return response()->json(['error' => 'Asunto no encontrado'], 404);
            }

            // Verificar si está siendo usado
            if ($asunto->expedientes()->count() > 0) {
                return response()->json([
                    'error' => 'No se puede eliminar el asunto porque está siendo usado en expedientes'
                ], 422);
            }

            $asunto->delete();
            
            Log::info("Asunto eliminado: ID $id");
            
            return response()->json(['message' => 'Asunto eliminado correctamente']);
            
        } catch (Exception $e) {
            Log::error('Error en destroy', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => 'Error al eliminar asunto',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}