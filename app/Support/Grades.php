<?php

namespace App\Support;

class Grades
{
    /**
     * Devuelve la calificación efectiva:
     * - 'J' => 10
     * - 'P' => null (no cuenta)
     * - normal => $score
     */
    public static function effective(?string $status, $score): ?float
    {
        if ($status === 'J') {
            return 10.0;
        }
        if ($status === 'P') {
            return null;
        }
        if ($score === null) {
            return null;
        }
        return (float) $score;
    }

    /**
     * Clase CSS para resaltar filas:
     * - amarillo si 'P'
     * - rojo si efectiva == 0
     */
    public static function rowClass(?string $status, $effective): string
    {
        if ($status === 'P') {
            return 'bg-yellow-50';
        }

        if (is_numeric($effective) && (float) $effective === 0.0) {
            return 'bg-red-50';
        }

        return '';
    }
}
