<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; 

class AsuntoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            DB::table('asuntos')->insert([
                'id_asunto' => $index + 1,
                'nombre_asunto' => $asunto,
                'documento_id' => 1, // Solicitud
                'activo' => 1
            ]);
        }

        // 3. ASUNTOS PARA OFICIOS (según tu BD)
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
            DB::table('asuntos')->insert([
                'id_asunto' => $startId + $index,
                'nombre_asunto' => $asunto,
                'documento_id' => 2, // Oficio
                'activo' => 1
            ]);
        }
    }
    
}