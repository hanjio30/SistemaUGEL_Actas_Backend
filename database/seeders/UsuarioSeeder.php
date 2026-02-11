<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            [
                'dni' => '12345678',
                'nombre_completo' => 'Administrador Principal',
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
                'dni' => '87654321',
                'nombre_completo' => 'Colaborador 1',
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
        ];

        foreach ($usuarios as $usuario) {
            // Verificar si ya existe por DNI
            $existe = DB::table('usuario')->where('dni', $usuario['dni'])->exists();
            
            if (!$existe) {
                DB::table('usuario')->insert($usuario);
            }
        }
    }
}