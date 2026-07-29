<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Contracts\View\View;

class MapController extends Controller
{
    public function __invoke(): View
    {
        $stations = Station::with('latestReading')
            ->orderBy('name')
            ->get()
            ->map(fn (Station $station): array => [
                'name' => $station->name,
                'river' => $station->river,
                'municipality' => $station->municipality,
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'unit' => $station->unit,
                'alertLevel' => $station->alert_level,
                'criticalLevel' => $station->critical_level,
                'status' => $station->status(),
                'source' => $station->source,
                'reading' => $station->latestReading === null ? null : [
                    'value' => $station->latestReading->value,
                    // Sempre acompanhado do instante: leitura sem hora não circula.
                    'measuredAt' => $station->latestReading->measured_at->toIso8601String(),
                    'stale' => $station->latestReading->isStale(),
                ],
            ]);

        return view('map', ['stations' => $stations]);
    }
}
