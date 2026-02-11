<?php
// app/Models/HistorialExpediente.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialExpediente extends Model
{
    protected $table = 'historial_expediente';
    protected $primaryKey = 'id_historial';
    public $timestamps = false;

    protected $fillable = [
        'expediente_id',
        'usuario',
        'estado_anterior',
        'estado_nuevo',
        'observaciones',
        'fecha_cambio'
    ];

    protected $casts = [
        'fecha_cambio' => 'datetime'
    ];

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'expediente_id', 'id_expediente');
    }
}