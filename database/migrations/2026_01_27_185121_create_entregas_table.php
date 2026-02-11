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
        Schema::create('entregas', function (Blueprint $table) {
            $table->id('id_entrega');
            $table->unsignedBigInteger('expediente_id');
            $table->string('dni_recoge', 8);
            $table->enum('tipo_recogida', ['titular', 'tercero'])->default('titular');
            $table->string('nombre_autorizado')->nullable();
            $table->string('dni_autorizado', 8)->nullable();
            $table->string('documento_autorizacion')->nullable(); // Ruta del archivo PDF
            $table->text('observaciones')->nullable();
            $table->dateTime('fecha_entrega');
            $table->time('hora_entrega');
            $table->integer('dias_atencion'); // Tiempo de atención en días
            $table->string('entregado_por')->nullable(); // Nombre del funcionario
            $table->timestamps();

            // Foreign key
            $table->foreign('expediente_id')
                  ->references('id_expediente')
                  ->on('expedientes')
                  ->onDelete('cascade');
            
            // Índices para búsquedas rápidas
            $table->index('expediente_id');
            $table->index('fecha_entrega');
            $table->index('tipo_recogida');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};