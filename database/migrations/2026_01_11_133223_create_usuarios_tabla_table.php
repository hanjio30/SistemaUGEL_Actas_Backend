<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->id();
            
            // Datos de identificación
            $table->string('nombre_completo', 255);
            $table->string('dni', 8)->unique();
            $table->string('telefono', 15)->nullable();
            
            // Datos de acceso
            $table->string('usuario', 100)->unique();
            $table->string('contrasena', 255);
            $table->string('correo', 255)->unique();
            
            // Rol y estado
            $table->enum('rol', ['Administrador', 'Colaborador'])->default('Colaborador');
            $table->enum('estado', ['Activo', 'Inactivo'])->default('Activo');
            
            // Información adicional
            $table->string('foto_perfil', 255)->nullable();
            $table->timestamp('ultimo_acceso')->nullable();
            
            // Timestamps automáticos
            $table->timestamps();
            
            // Índices para mejorar rendimiento
            $table->index('usuario');
            $table->index('dni');
            $table->index('correo');
            $table->index('estado');
            $table->index(['rol', 'estado']); // Índice compuesto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};