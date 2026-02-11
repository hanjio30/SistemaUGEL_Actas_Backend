<?php
// app/Http/Controllers/Api/SolicitanteController.php
namespace App\Http\Controllers;

use App\Models\Solicitante;
use Illuminate\Http\Request;

class SolicitanteController extends Controller
{
    public function index(Request $request)
    {
        $query = Solicitante::query();
        
        // Buscar por DNI
        if ($request->has('dni')) {
            $query->where('dni', $request->dni);
        }
        
        // Buscar por Código Modular
        if ($request->has('codigo_modular')) {
            $query->where('codigo_modular', $request->codigo_modular);
        }
        
        // Buscar por tipo
        if ($request->has('nombre_tipo')) {
            $query->where('nombre_tipo', $request->nombre_tipo);
        }
        
        // Búsqueda por nombre (parcial)
        if ($request->has('nombre')) {
            $query->where('nombre_solicitante', 'LIKE', '%' . $request->nombre . '%');
        }
        
        $solicitantes = $query->get();
        return response()->json($solicitantes);
    }

    public function show($id)
    {
        $solicitante = Solicitante::with('expedientes')->find($id);
        
        if (!$solicitante) {
            return response()->json(['error' => 'Solicitante no encontrado'], 404);
        }
        
        return response()->json($solicitante);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_solicitante' => 'required|string|max:255',
            'dni' => 'nullable|string|size:8|unique:solicitante,dni',
            'codigo_modular' => 'nullable|string|max:20|unique:solicitante,codigo_modular',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'nombre_tipo' => 'required|in:Natural,Jurídica'
        ]);

        // Verificar que al menos DNI o Código Modular esté presente
        if (empty($validated['dni']) && empty($validated['codigo_modular'])) {
            return response()->json([
                'error' => 'Debe proporcionar DNI o Código Modular'
            ], 422);
        }

        // Verificar si ya existe
        $existente = Solicitante::where(function($query) use ($validated) {
            if (!empty($validated['dni'])) {
                $query->orWhere('dni', $validated['dni']);
            }
            if (!empty($validated['codigo_modular'])) {
                $query->orWhere('codigo_modular', $validated['codigo_modular']);
            }
        })->first();

        if ($existente) {
            // Si existe, retornar el existente
            return response()->json($existente, 200);
        }

        // Si no existe, crear nuevo
        $solicitante = Solicitante::create($validated);
        return response()->json($solicitante, 201);
    }

    public function update(Request $request, $id)
    {
        $solicitante = Solicitante::find($id);
        
        if (!$solicitante) {
            return response()->json(['error' => 'Solicitante no encontrado'], 404);
        }

        $validated = $request->validate([
            'nombre_solicitante' => 'sometimes|string|max:255',
            'dni' => 'nullable|string|size:8|unique:solicitante,dni,' . $id . ',id_solicitante',
            'codigo_modular' => 'nullable|string|max:20|unique:solicitante,codigo_modular,' . $id . ',id_solicitante',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'nombre_tipo' => 'sometimes|in:Natural,Jurídica'
        ]);

        $solicitante->update($validated);
        return response()->json($solicitante);
    }

    public function destroy($id)
    {
        $solicitante = Solicitante::find($id);
        
        if (!$solicitante) {
            return response()->json(['error' => 'Solicitante no encontrado'], 404);
        }

        $solicitante->delete();
        return response()->json(['message' => 'Solicitante eliminado correctamente']);
    }
}
