<?php

namespace App\Services;

use Illuminate\Support\Collection;

class Odontogram
{
    public const COLORS = [
        'Sin registro' => '#f1f5f9', 'Sano' => '#d1fae5', 'Caries' => '#fca5a5',
        'Restaurado' => '#93c5fd', 'Ausente' => '#cbd5e1', 'Endodoncia' => '#c4b5fd',
        'Corona' => '#fde68a',
    ];

    public static function current(Collection $history): Collection
    {
        // A whole-tooth entry replaces previous surfaces; later surface entries refine it.
        return $history->sortByDesc('id')->groupBy('tooth')->flatMap(function ($entries) {
            $whole = $entries->firstWhere('surface', 'Completa');
            return $entries->filter(fn ($entry) => !$whole || $entry->id >= $whole->id)
                ->unique('surface')->values();
        })->values();
    }
}
