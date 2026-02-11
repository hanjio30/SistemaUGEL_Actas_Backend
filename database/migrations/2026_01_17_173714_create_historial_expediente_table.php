<?php
// database/migrations/2026_01_17_create_historial_expediente_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('historial_expediente', function (Blueprint $table) {
            $table->id('id_historial');
            $table->unsignedBigInteger('expediente_id');
            $table->string('usuario', 255); // Nombre del colaborador
            $table->string('estado_anterior', 50)->nullable();
            $table->string('estado_nuevo', 50);
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_cambio')->useCurrent();
            
            // CORRECCIÓN: Referencia a la tabla "expedientes" (plural)
            $table->foreign('expediente_id')
                  ->references('id_expediente')
                  ->on('expedientes')  // ← CAMBIO AQUÍ: plural
                  ->onDelete('cascade');
            
            $table->index('expediente_id');
            $table->index('fecha_cambio');
        });
    }

    public function down()
    {
        Schema::dropIfExists('historial_expediente');
    }
};