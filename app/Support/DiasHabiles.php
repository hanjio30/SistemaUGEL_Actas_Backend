<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DiasHabiles
{
    // Feriados fijos del Perú (mes-día)
    private const FERIADOS_FIJOS = [
        '01-01', '05-01', '06-07', '06-29', '07-23', '07-28', '07-29',
        '08-06', '08-30', '10-08', '11-01', '12-08', '12-09', '12-25', '11-10',
        '11-11',
    ];

    // Días no laborables o feriados extra decretados (YYYY-MM-DD).
    // Mantener sincronizado con FERIADOS_EXTRA de src/utils/diasHabiles.js
    private const FERIADOS_EXTRA = [
        // '2026-12-24',
        // '2026-12-31',
    ];

    public static function esFeriado(CarbonInterface $fecha): bool
    {
        if (in_array($fecha->format('m-d'), self::FERIADOS_FIJOS, true)) {
            return true;
        }

        if (in_array($fecha->format('Y-m-d'), self::FERIADOS_EXTRA, true)) {
            return true;
        }

        // Jueves y Viernes Santo
        $anio = (int) $fecha->format('Y');
        $pascua = Carbon::create($anio, 3, 21)->addDays(easter_days($anio))->startOfDay();

        $juevesSanto = $pascua->copy()->subDays(3)->format('Y-m-d');
        $viernesSanto = $pascua->copy()->subDays(2)->format('Y-m-d');
        $clave = $fecha->format('Y-m-d');

        return $clave === $juevesSanto || $clave === $viernesSanto;
    }

    public static function esDiaHabil(CarbonInterface $fecha): bool
    {
        return !$fecha->isWeekend() && !self::esFeriado($fecha);
    }

    /**
     * Días hábiles transcurridos desde $inicio hasta $fin (por defecto hoy).
     * El día de recepción cuenta 0; igual que en el frontend.
     */
    public static function contar($inicio, $fin = null): int
    {
        $cursor = Carbon::parse($inicio)->startOfDay();
        $fin = $fin ? Carbon::parse($fin)->startOfDay() : Carbon::today();

        $dias = 0;
        while ($cursor->lt($fin)) {
            $cursor->addDay();
            if (self::esDiaHabil($cursor)) {
                $dias++;
            }
        }

        return $dias;
    }
}