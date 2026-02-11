<?php
// app/Http/Controllers/DocumentoController.php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Http\Request;

class DocumentoController extends Controller
{
    public function index()
    {
        $documentos = Documento::all();
        return response()->json($documentos);
    }

    public function show($id)
    {
        $documento = Documento::find($id);
        
        if (!$documento) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        
        return response()->json($documento);
    }
}

