<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; 

class DocumentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('documento')->insert([
            ['id_documento' => 1, 'nombre_tipo' => 'Solicitud'],
            ['id_documento' => 2, 'nombre_tipo' => 'Oficio']
        ]);


    }
}
