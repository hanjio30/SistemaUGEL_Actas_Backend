<?php
// app/Models/Solicitante.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitante extends Model
{
    protected $table = 'solicitante';
    protected $primaryKey = 'id_solicitante';
    public $timestamps = false;
    protected $fillable = [
        'nombre_solicitante',
        'dni',
        'codigo_modular',
        'email',
        'telefono',
        'nombre_tipo'
    ];

    public function expedientes()
    {
        return $this->hasMany(Expediente::class, 'solicitante_id', 'id_solicitante');
    }
}