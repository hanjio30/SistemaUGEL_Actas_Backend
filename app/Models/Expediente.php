<?php
// app/Models/Expediente.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expediente extends Model
{
    protected $table = 'expedientes';
    protected $primaryKey = 'id_expediente';
    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;

    protected $fillable = [
        'num_expediente',
        'firma_ruta',
        'solicitante_id',
        'asunto_id',
        'fecha_recepcion',
        'estado',
        'observaciones'
    ];

    protected $casts = [
        'fecha_recepcion' => 'date',
        'fecha_creacion' => 'datetime'
    ];

    public function solicitante()
    {
        return $this->belongsTo(Solicitante::class, 'solicitante_id', 'id_solicitante');
    }

    public function asunto()
    {
        return $this->belongsTo(Asunto::class, 'asunto_id', 'id_asunto');
    }

    public function historial()
    {
        return $this->hasMany(HistorialExpediente::class, 'expediente_id', 'id_expediente')
                    ->orderBy('fecha_cambio', 'desc');
    }

    public function atenciones()
    {
        return $this->hasMany(Atencion::class, 'id_expediente', 'id_expediente');
    }

    /**
     * Obtener el nombre del usuario actual desde diferentes fuentes
     */
    protected static function obtenerUsuarioActual()
    {
        // 1. Primero intentar desde el request (enviado desde el frontend)
        if (request()->has('usuario')) {
            return request()->input('usuario');
        }

        // 2. Luego intentar desde la sesión de Laravel
        if (session()->has('usuario')) {
            $usuarioData = session('usuario');
            return $usuarioData['usuario'] ?? $usuarioData['nombre'] ?? null;
        }

        // 3. Si existe autenticación de Laravel
        if (auth()->check()) {
            return auth()->user()->name ?? auth()->user()->usuario ?? null;
        }

        // 4. Por defecto
        return 'Sistema';
    }

    // Observer para registrar cambios automáticamente
    protected static function boot()
    {
        parent::boot();
        
        // Registrar creación
        static::created(function ($expediente) {
            $usuario = self::obtenerUsuarioActual();
            
            HistorialExpediente::create([
                'expediente_id' => $expediente->id_expediente,
                'usuario' => $usuario,
                'estado_anterior' => null,
                'estado_nuevo' => $expediente->estado,
                'observaciones' => 'Expediente registrado. Código: ' . $expediente->firma_ruta,
                'fecha_cambio' => now()
            ]);
        });
        
        // Registrar actualizaciones
        static::updated(function ($expediente) {
            if ($expediente->isDirty('estado')) {
                $usuario = self::obtenerUsuarioActual();
                
                HistorialExpediente::create([
                    'expediente_id' => $expediente->id_expediente,
                    'usuario' => $usuario,
                    'estado_anterior' => $expediente->getOriginal('estado'),
                    'estado_nuevo' => $expediente->estado,
                    'observaciones' => $expediente->observaciones ?? 'Estado actualizado',
                    'fecha_cambio' => now()
                ]);
            }
        });
    }

     /**
     * Relación con Entregas
     * ⚠️ CRÍTICO: Esta relación era la que faltaba
     */
    public function entregas()
    {
        return $this->hasMany(Entrega::class, 'expediente_id', 'id_expediente');
    }

    /**
     * Scopes para filtros comunes
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_recepcion', [$fechaInicio, $fechaFin]);
    }

    public function scopeObservados($query)
    {
        return $query->where('estado', 'OBSERVADO');
    }

    public function scopePendientes($query)
    {
        return $query->whereNotIn('estado', ['ENTREGADO']);
    }

}