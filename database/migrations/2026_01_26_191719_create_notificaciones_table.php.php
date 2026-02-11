<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla de notificaciones
 * 
 * Esta tabla almacena todas las notificaciones enviadas a los usuarios
 * permitiendo:
 * - Ver historial de notificaciones
 * - Marcar como leído/no leído
 * - Implementar un centro de notificaciones en el sistema
 * 
 * Para usar:
 * 1. Copia este archivo a: database/migrations/
 * 2. Renombra como: YYYY_MM_DD_HHMMSS_create_notificaciones_table.php
 *    Por ejemplo: 2026_01_26_190000_create_notificaciones_table.php
 * 3. Ejecuta: php artisan migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id('id_notificacion');
            
            // Relación con la tabla usuario (no users)
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id')
                  ->references('id')
                  ->on('usuario')
                  ->onDelete('cascade');
            
            // Tipo de notificación
            $table->enum('tipo', [
                'vencimientos_observados',
                'expediente_actualizado',
                'expediente_nuevo',
                'general'
            ])->default('general');
            
            // Contenido
            $table->string('titulo', 255);
            $table->text('mensaje');
            
            // Datos adicionales en JSON (expedientes relacionados, etc.)
            $table->json('datos')->nullable();
            
            // Estado de lectura
            $table->boolean('leida')->default(false);
            $table->timestamp('fecha_lectura')->nullable();
            
            // Prioridad
            $table->enum('prioridad', ['baja', 'media', 'alta', 'urgente'])->default('media');
            
            // Timestamps
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_actualizacion')->useCurrent()->useCurrentOnUpdate();
            
            // Índices para mejorar las consultas
            $table->index('usuario_id');
            $table->index('tipo');
            $table->index('leida');
            $table->index(['usuario_id', 'leida']); // Para consultas de "no leídas por usuario"
            $table->index('fecha_creacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};