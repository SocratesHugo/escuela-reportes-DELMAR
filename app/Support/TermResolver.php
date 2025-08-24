<?php

namespace App\Support;

use App\Models\Week;
use Illuminate\Support\Carbon;

/**
 * Resuelve el trimestre (1|2|3) de una Week.
 * Si tu modelo Week tiene columna 'term'/'trimester'/'term_id', la usa.
 * Si no, hace una suposición por mes (ajústala a tu calendario escolar).
 */
final class TermResolver
{
    public static function forWeek(Week $week): int
    {
        // 1) Campos típicos
        foreach (['term', 'trimester', 'term_id', 'quarter'] as $col) {
            if (isset($week->{$col}) && (int) $week->{$col} >= 1 && (int) $week->{$col} <= 3) {
                return (int) $week->{$col};
            }
        }

        // 2) Heurística por mes de starts_at
        $date = $week->starts_at ? Carbon::parse($week->starts_at) : null;
        if (!$date) return 1;

        $m = (int) $date->format('n'); // 1..12

        // Ajusta a tu ciclo:
        // T1: Ago–Nov (8–11), T2: Dic–Mar (12,1,2,3), T3: Abr–Jul (4–7)
        if ($m >= 8 && $m <= 11) return 1;
        if (in_array($m, [12,1,2,3], true)) return 2;
        return 3; // 4–7
    }
}
