<?php
// app/Models/Documento.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    protected $table = 'documento';
    protected $primaryKey = 'id_documento';
    public $timestamps = false;
    protected $fillable = ['nombre_tipo'];

    public function asuntos()
    {
        return $this->hasMany(Asunto::class, 'documento_id', 'id_documento');
    }
}