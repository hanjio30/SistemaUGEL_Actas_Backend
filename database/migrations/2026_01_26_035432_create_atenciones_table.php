<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('atenciones', function (Blueprint $table) {
            $table->id('id_atencion');
            $table->unsignedBigInteger('id_expediente');
            $table->string('usuario', 100); // Nombre del usuario que atiende
            $table->string('estado_anterior', 50);
            $table->string('estado_nuevo', 50);
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_atencion')->useCurrent();
            $table->timestamps();

            // Relación con expedientes
            $table->foreign('id_expediente')
                  ->references('id_expediente')
                  ->on('expedientes')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('atenciones');
    }
};