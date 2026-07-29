<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Contracts\View\View;

class MapController extends Controller
{
    public function __invoke(): View
    {
        // Só vai ao mapa quem tem leitura própria. Estação catalogada sem medição
        // não informa nada — vira ruído sobre as que informam.
        $stations = Station::with('latestReading')
            ->whereHas('readings')
            ->orderBy('name')
            ->get()
            ->map(fn (Station $station): array => [
                'name' => $station->name,
                'river' => $station->river,
                'municipality' => $station->municipality,
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'unit' => $station->unit,
                'attentionLevel' => $station->attention_level,
                'alertLevel' => $station->alert_level,
                'criticalLevel' => $station->critical_level,
                'status' => $station->status(),
                'reading' => [
                    'value' => $station->latestReading->value,
                    // Sempre acompanhado do instante: leitura sem hora não circula.
                    'measuredAt' => $station->latestReading->measured_at->toIso8601String(),
                    'stale' => $station->latestReading->isStale(),
                    'source' => $station->latestReading->source,
                ],
            ]);

        return view('map', ['stations' => $stations]);
    }
}
