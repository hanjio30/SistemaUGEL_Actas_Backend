<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('usuario')->insert([
            [
                'nombre_completo' => 'Administrador Principal',
                'dni' => '12345678',
                'telefono' => '987654321',
                'usuario' => 'admin',
                'contrasena' => Hash::make('admin123'),
                'correo' => 'admin@ugelsanta.gob.pe',
                'rol' => 'Administrador',
                'estado' => 'Activo',
                'foto_perfil' => null,
                'ultimo_acceso' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'nombre_completo' => 'Colaborador 1',
                'dni' => '87654321',
                'telefono' => '987654322',
                'usuario' => 'colaborador1',
                'contrasena' => Hash::make('colab123'),
                'correo' => 'colaborador@ugelsanta.gob.pe',
                'rol' => 'Colaborador',
                'estado' => 'Activo',
                'foto_perfil' => null,
                'ultimo_acceso' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
