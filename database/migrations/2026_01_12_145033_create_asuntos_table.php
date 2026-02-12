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
        Schema::create('asuntos', function (Blueprint $table) {
            $table->id('id_asunto');
            $table->string('nombre_asunto', 255);
            $table->unsignedBigInteger('documento_id');
            $table->boolean('activo')->default(true);
             $table->timestamps(); 
            
            // Foreign key
            $table->foreign('documento_id')
                  ->references('id_documento')
                  ->on('documento')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asuntos');
    }
};