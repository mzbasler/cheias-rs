<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Contracts\View\View;

class MapController extends Controller
{
    public function __invoke(): View
    {
        $stations = Station::with(['latestReading', 'todayDischarge', 'dischargeForecast'])
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
                'discharge' => $station->todayDischarge === null ? null : [
                    'value' => $station->todayDischarge->value,
                    'day' => $station->todayDischarge->measured_at->toDateString(),
                    'trend' => $this->dischargeTrend($station),
                ],
            ]);

        return view('map', ['stations' => $stations]);
    }

    /**
     * Compara a vazão de hoje com a do último dia previsto: 'rising', 'falling'
     * ou 'steady'. O limiar de 10% evita chamar de tendência o ruído do modelo.
     */
    private function dischargeTrend(Station $station): ?string
    {
        $forecast = $station->dischargeForecast;

        if ($forecast->count() < 2) {
            return null;
        }

        $first = $forecast->first()->value;
        $last = $forecast->last()->value;

        if ($first <= 0.0) {
            return null;
        }

        $change = ($last - $first) / $first;

        return match (true) {
            $change >= 0.10 => 'rising',
            $change <= -0.10 => 'falling',
            default => 'steady',
        };
    }
}
