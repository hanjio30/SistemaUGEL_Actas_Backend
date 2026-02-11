<?php
// app/Models/Asunto.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asunto extends Model
{
    protected $table = 'asuntos';
    protected $primaryKey = 'id_asunto';
    public $timestamps = false;
    protected $fillable = ['nombre_asunto', 'documento_id', 'activo'];

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'documento_id', 'id_documento');
    }

    public function expedientes()
    {
        return $this->hasMany(Expediente::class, 'asunto_id', 'id_asunto');
    }
}