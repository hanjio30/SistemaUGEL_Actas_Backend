<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entrega extends Model
{
    protected $table = 'entregas';
    protected $primaryKey = 'id_entrega';
    
    protected $fillable = [
        'expediente_id',
        'dni_recoge',
        'tipo_recogida',
        'nombre_autorizado',
        'dni_autorizado',
        'documento_autorizacion',
        'observaciones',
        'fecha_entrega',
        'hora_entrega',
        'dias_atencion',
        'entregado_por'
    ];

    protected $casts = [
        'fecha_entrega' => 'datetime',
        'hora_entrega' => 'datetime'
    ];

    /**
     * Relación con Expediente
     */
    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'expediente_id', 'id_expediente');
    }

    /**
     * Obtener el nombre completo de quien recoge
     */
    public function getNombreRecogeAttribute()
    {
        if ($this->tipo_recogida === 'tercero') {
            return $this->nombre_autorizado;
        }
        return $this->expediente->solicitante->nombre_solicitante ?? 'No disponible';
    }

    /**
     * Obtener el DNI efectivo de quien recoge
     */
    public function getDniEfectivoAttribute()
    {
        if ($this->tipo_recogida === 'tercero') {
            return $this->dni_autorizado;
        }
        return $this->dni_recoge;
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeFechaEntre($query, $inicio, $fin)
    {
        return $query->whereBetween('fecha_entrega', [$inicio, $fin]);
    }

    /**
     * Scope para filtrar por tipo de recogida
     */
    public function scopeTipoRecogida($query, $tipo)
    {
        return $query->where('tipo_recogida', $tipo);
    }
}