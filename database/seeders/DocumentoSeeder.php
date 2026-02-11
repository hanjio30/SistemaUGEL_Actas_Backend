<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $documentos = [
            ['id_documento' => 1, 'nombre_tipo' => 'Solicitud'],
            ['id_documento' => 2, 'nombre_tipo' => 'Oficio']
        ];

        foreach ($documentos as $documento) {
            // Verificar si ya existe por id_documento
            $existe = DB::table('documento')
                ->where('id_documento', $documento['id_documento'])
                ->exists();
            
            if (!$existe) {
                DB::table('documento')->insert($documento);
            }
        }
    }
}