<?php
// database/migrations/2026_01_29_fix_asuntos_sequence.php
// Ejecuta: php artisan make:migration fix_asuntos_sequence
// Luego reemplaza el contenido con este código
// Y ejecuta: php artisan migrate

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Arreglar la secuencia automáticamente
        if (config('database.default') === 'pgsql') {
            DB::statement("
                SELECT setval(
                    pg_get_serial_sequence('asuntos', 'id_asunto'),
                    (SELECT COALESCE(MAX(id_asunto), 0) + 1 FROM asuntos),
                    false
                )
            ");
        }
    }

    public function down()
    {
        // No es necesario revertir
    }
};