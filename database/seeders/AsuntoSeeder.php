<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AsuntoSeeder extends Seeder
{
    public function run(): void
    {
        // Asuntos para Solicitudes
        $asuntosSolicitud = [
            'Visación de Certificados',
            'Expedición de Certificados',
            'Visación de 5 Primeros Puestos',
            'Visación de Certificados Modulares',
            'Prueba de Ubicación',
            'Regularización de Escolaridad',
            'Rectificación de Acta',
            'Rectificación de Nombre',
            'Rectificación de Nombre y Apellido',
            'Nuevos Títulos',
            'Otros',
            'Actas Finales'
        ];

        foreach ($asuntosSolicitud as $index => $asunto) {
            $id = $index + 1;
            
            // Verificar si ya existe
            $existe = DB::table('asuntos')->where('id_asunto', $id)->exists();
            
            if (!$existe) {
                DB::table('asuntos')->insert([
                    'id_asunto' => $id,
                    'nombre_asunto' => $asunto,
                    'documento_id' => 1, // Solicitud
                    'activo' => 1
                ]);
            }
        }

        // Asuntos para Oficios
        $asuntosOficio = [
            'Actas de Evaluación',
            'Actas de Subsanación',
            'Actas de Recuperación',
            'Nómina de Matrícula',
            'Acta de Convalidación',
            'Acta de Convalidación y Revalidación',
            'Registro de Firmas'
        ];

        $startId = count($asuntosSolicitud) + 1;
        
        foreach ($asuntosOficio as $index => $asunto) {
            $id = $startId + $index;
            
            // Verificar si ya existe
            $existe = DB::table('asuntos')->where('id_asunto', $id)->exists();
            
            if (!$existe) {
                DB::table('asuntos')->insert([
                    'id_asunto' => $id,
                    'nombre_asunto' => $asunto,
                    'documento_id' => 2, // Oficio
                    'activo' => 1
                ]);
            }
        }
    }
}