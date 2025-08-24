<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class Grades
{
    public static function effectiveScore($score, ?string $status): ?float
    {
        if ($status === 'P') return 0.0;
        if ($status === 'J') return 10.0;
        if ($score === null || $score === '') return null;
        return (float) $score;
    }

    public static function isPending($score, ?string $status): bool
    {
        return ($status === 'P');
    }

    public static function isMissingZero($score, ?string $status): bool
    {
        if ($status === 'P' || $status === 'J') return false;
        if ($score === null || $score === '') return false;
        return ((float) $score) == 0.0;
    }

    public static function isDelivered($score, ?string $status): bool
    {
        if ($status === 'J') return true;
        if ($status === 'P') return false;
        if ($score === null || $score === '') return false;
        return (float) $score >= 1.0;
    }

    public static function badge($score, ?string $status): array
    {
        if ($status === 'P') return ['P', 'bg-yellow-100 text-yellow-800'];
        if ($status === 'J') return ['J', 'bg-blue-100 text-blue-800'];
        if (self::isMissingZero($score, $status)) return ['Sin entregar', 'bg-rose-100 text-rose-800'];
        if (self::isDelivered($score, $status))    return ['Entregado', 'bg-green-100 text-green-800'];
        return ['normal', 'bg-slate-100 text-slate-800'];
    }

    public static function progress(Collection $works, Collection $grades): array
    {
        $delivered = 0; $pending = 0; $missing = 0;
        foreach ($works as $w) {
            $g = $grades->get($w->id);
            $score  = $g->score  ?? null;
            $status = $g->status ?? null;
            if (self::isPending($score, $status))        $pending++;
            elseif (self::isMissingZero($score, $status)) $missing++;
            elseif (self::isDelivered($score, $status))   $delivered++;
        }
        $deliverable = $delivered + $pending + $missing;
        return compact('delivered','pending','missing','deliverable');
    }
}
