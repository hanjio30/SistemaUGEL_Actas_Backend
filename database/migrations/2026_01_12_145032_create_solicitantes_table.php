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
        Schema::create('solicitante', function (Blueprint $table) {
            $table->id('id_solicitante');
            $table->string('nombre_solicitante', 255);
            $table->string('dni', 8)->nullable()->unique();
            $table->string('codigo_modular', 20)->nullable()->unique();
            $table->string('email', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->enum('nombre_tipo', ['Natural', 'Jurídica']); // Natural o Jurídica
            
            // Índices para búsquedas rápidas
            $table->index('dni');
            $table->index('codigo_modular');
            $table->index('nombre_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitante');
    }
};