<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Usuario extends Model
{
    protected $table = 'usuario';

    protected $fillable = [
        'nombre_completo',
        'dni',
        'telefono',
        'usuario',
        'contrasena',
        'correo',
        'rol',
        'estado',
        'foto_perfil',
        'ultimo_acceso'
    ];

    protected $hidden = [
        'contrasena'
    ];

    protected $casts = [
        'ultimo_acceso' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==========================================
    // MUTADORES
    // ==========================================
    
    /**
     * Hashear contraseña automáticamente al asignar
     */
    public function setContrasenaAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['contrasena'] = Hash::make($value);
        }
    }

    // ==========================================
    // SCOPES
    // ==========================================
    
    /**
     * Obtener solo usuarios activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'Activo');
    }

    /**
     * Obtener solo usuarios inactivos
     */
    public function scopeInactivos($query)
    {
        return $query->where('estado', 'Inactivo');
    }

    /**
     * Obtener solo administradores
     */
    public function scopeAdministradores($query)
    {
        return $query->where('rol', 'Administrador');
    }

    /**
     * Obtener solo colaboradores
     */
    public function scopeColaboradores($query)
    {
        return $query->where('rol', 'Colaborador');
    }

    // ==========================================
    // RELACIONES
    // ==========================================
    
    /**
     * Expedientes atendidos por el usuario
     */
    public function expedientesAtendidos()
    {
        return $this->hasMany(Atencion::class, 'usuario', 'usuario');
    }

    /**
     * Notificaciones del usuario
     */
    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'usuario_id', 'id');
    }

    /**
     * Entregas realizadas por el usuario
     */
    public function entregas()
    {
        return $this->hasMany(Entrega::class, 'entregado_por', 'usuario');
    }

    // ==========================================
    // MÉTODOS AUXILIARES
    // ==========================================
    
    /**
     * Obtener notificaciones no leídas
     */
    public function notificacionesNoLeidas()
    {
        return $this->notificaciones()
            ->noLeidas()
            ->orderBy('fecha_creacion', 'desc');
    }

    /**
     * Contar notificaciones no leídas
     */
    public function contarNoLeidas()
    {
        return $this->notificaciones()->noLeidas()->count();
    }

    /**
     * Verificar si el usuario es administrador
     */
    public function esAdministrador()
    {
        return $this->rol === 'Administrador';
    }

    /**
     * Verificar si el usuario está activo
     */
    public function estaActivo()
    {
        return $this->estado === 'Activo';
    }

    /**
     * Activar usuario
     */
    public function activar()
    {
        $this->estado = 'Activo';
        return $this->save();
    }

    /**
     * Desactivar usuario
     */
    public function desactivar()
    {
        $this->estado = 'Inactivo';
        return $this->save();
    }

    /**
     * Actualizar último acceso
     */
    public function actualizarUltimoAcceso()
    {
        $this->ultimo_acceso = now();
        return $this->save();
    }

    /**
     * Obtener iniciales del nombre
     */
    public function getInicialesAttribute()
    {
        $palabras = explode(' ', $this->nombre_completo);
        if (count($palabras) >= 2) {
            return strtoupper(substr($palabras[0], 0, 1) . substr($palabras[1], 0, 1));
        }
        return strtoupper(substr($this->nombre_completo, 0, 2));
    }

    /**
     * Obtener foto de perfil o avatar por defecto
     */
    public function getFotoPerfilUrlAttribute()
    {
        if ($this->foto_perfil && file_exists(public_path($this->foto_perfil))) {
            return asset($this->foto_perfil);
        }
        
        // Avatar por defecto con iniciales
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->nombre_completo) . 
               '&background=083f8f&color=ffffff&size=200';
    }

    /**
     * Obtener tiempo desde último acceso
     */
    public function getTiempoUltimoAccesoAttribute()
    {
        if (!$this->ultimo_acceso) {
            return 'Nunca';
        }
        
        return $this->ultimo_acceso->diffForHumans();
    }
}