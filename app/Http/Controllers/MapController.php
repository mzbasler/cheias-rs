<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Models\Station;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

class MapController extends Controller
{
    /** Janela usada para o pico do medidor e para a variação mostrada no card. */
    private const HISTORY_HOURS = 48;

    private const TREND_HOURS = 3;

    public function __invoke(): View
    {
        // Só vai ao mapa quem tem leitura própria. Estação catalogada sem medição
        // não informa nada — vira ruído sobre as que informam.
        $stations = Station::with(['latestReading', 'recentReadings'])
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
                'alertLevel' => $station->alertLevel(),
                'criticalLevel' => $station->critical_level,
                'status' => $station->status(),
                'reading' => [
                    'value' => $station->latestReading->value,
                    // Sempre acompanhado do instante: leitura sem hora não circula.
                    'measuredAt' => $station->latestReading->measured_at->toIso8601String(),
                    'stale' => $station->latestReading->isStale(),
                    'source' => $station->latestReading->source,
                ],
                // O medidor precisa de um topo, e a fonte não informa o leito nem a
                // margem do rio: usa-se o maior valor conhecido como referência.
                'peak' => $station->recentReadings->max('value'),
                'change' => $this->change($station->recentReadings),
            ]);

        return view('map', ['stations' => $stations]);
    }

    /**
     * Variação nas últimas horas, para dizer se o rio sobe ou desce. Devolve
     * null quando o histórico é curto demais para afirmar tendência.
     *
     * @param  Collection<int, Reading>  $readings
     * @return array{value: float, hours: float}|null
     */
    private function change(Collection $readings): ?array
    {
        $latest = $readings->last();

        if ($latest === null || $readings->count() < 2) {
            return null;
        }

        $target = $latest->measured_at->copy()->subHours(self::TREND_HOURS);

        $reference = $readings->last(fn (Reading $reading): bool => $reading->measured_at->lte($target))
            ?? $readings->first();

        $hours = $reference->measured_at->diffInMinutes($latest->measured_at) / 60;

        // Menos de 15 min entre as duas pontas não é tendência, é ruído.
        if ($hours < 0.25) {
            return null;
        }

        return [
            'value' => round($latest->value - $reference->value, 2),
            'hours' => round($hours, 1),
        ];
    }
}
