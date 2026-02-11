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
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id('id_expediente');
            $table->string('num_expediente', 50)->unique();
            $table->string('firma_ruta', 100)->nullable(); // Código de seguimiento
            $table->unsignedBigInteger('solicitante_id');
            $table->unsignedBigInteger('asunto_id');
            $table->date('fecha_recepcion');
            $table->string('estado', 50)->default('RECEPCIONADO');
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            
            // Foreign keys
            $table->foreign('solicitante_id')
                  ->references('id_solicitante')
                  ->on('solicitante')
                  ->onDelete('cascade');
            
            $table->foreign('asunto_id')
                  ->references('id_asunto')
                  ->on('asuntos')
                  ->onDelete('cascade');
            
            // Índices para búsquedas rápidas
            $table->index('num_expediente');
            $table->index('firma_ruta');
            $table->index('fecha_recepcion');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};