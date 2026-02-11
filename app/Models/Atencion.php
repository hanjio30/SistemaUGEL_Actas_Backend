<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atencion extends Model
{
    protected $table = 'atenciones';
    protected $primaryKey = 'id_atencion';

    protected $fillable = [
        'id_expediente',
        'usuario',
        'estado_anterior',
        'estado_nuevo',
        'observaciones',
        'fecha_atencion'
    ];

    protected $casts = [
        'fecha_atencion' => 'datetime'
    ];

    // Relación con Expediente
    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'id_expediente', 'id_expediente');
    }

    /**
     * Scopes
     */
    public function scopePorUsuario($query, $usuario)
    {
        return $query->where('usuario', $usuario);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_atencion', [$fechaInicio, $fechaFin]);
    }
}