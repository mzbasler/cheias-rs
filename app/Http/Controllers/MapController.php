<?php

namespace App\Http\Controllers;

use App\Models\Reading;
use App\Models\Report;
use App\Models\Setting;
use App\Models\Station;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

class MapController extends Controller
{
    /** Janela usada para o pico do medidor e para a variação mostrada no card. */
    private const HISTORY_HOURS = 48;

    private const TREND_HOURS = 3;

    /** Janela da seta de tendência no ponto do mapa — mais longa que a do
     *  card porque decide algo visível de longe, não um número exato. */
    private const DOT_TREND_HOURS = 24;

    public function __invoke(): View
    {
        // Só vai ao mapa quem tem leitura própria. As sem nenhuma são, na prática,
        // cadastro do SNIRH sem telemetria — nunca vão medir, e listadas viram
        // ruído sobre as 55 que informam de verdade.
        $stations = Station::with(['latestReading', 'recentReadings'])
            ->whereHas('readings')
            ->orderBy('name')
            ->get()
            ->map(fn (Station $station): array => [
                'id' => $station->id,
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
                'change' => $this->change($station->recentReadings, self::TREND_HOURS),
                'dotTrend' => $this->change($station->recentReadings, self::DOT_TREND_HOURS),
                'history' => $this->history($station->recentReadings),
            ]);

        // Relato aprovado é camada à parte da telemetria — nunca entra na lista
        // de $stations nem em 'reading', para não se misturar com medição oficial.
        $reports = Report::where('status', 'approved')
            ->get()
            ->map(fn (Report $report): array => [
                'id' => $report->id,
                'latitude' => $report->latitude,
                'longitude' => $report->longitude,
                'photoUrl' => $report->photoUrl(),
                'createdAt' => $report->created_at->toIso8601String(),
            ]);

        $setting = Setting::current();

        return view('map', [
            'stations' => $stations,
            'reports' => $reports,
            // Total do catálogo, para o aviso dizer quantas ficaram de fora por
            // nunca terem reportado — sem isso, o número de estações no mapa
            // pareceria o total, e não uma fração dele.
            'catalogTotal' => Station::count(),
            // Proveniência à vista no aviso de entrada: quantas estações cada
            // fonte trouxe. Contado agora, nunca escrito à mão — número fixo em
            // texto mente na primeira importação.
            'sources' => Station::selectRaw('source, count(*) as total')
                ->groupBy('source')
                ->pluck('total', 'source'),
            // Chave vazia é estado válido: o botão de doação vira aviso de "não
            // configurado" em vez de gerar QR Code para lugar nenhum.
            'pix' => [
                'key' => $setting->pix_key,
                'name' => $setting->pix_receiver_name ?? 'Cheias RS',
                'city' => $setting->pix_receiver_city ?? 'PORTO ALEGRE',
            ],
        ]);
    }

    /**
     * Série das últimas 24 h para o gráfico, como pares [epoch, valor] — o par
     * ocupa uma fração do que um objeto ocuparia, e são milhares de pontos.
     *
     * @param  Collection<int, Reading>  $readings
     * @return list<array{0: int, 1: float}>
     */
    private function history(Collection $readings): array
    {
        $since = now()->subDay();

        return $readings
            ->filter(fn (Reading $reading): bool => $reading->measured_at->gte($since))
            ->map(fn (Reading $reading): array => [
                $reading->measured_at->getTimestamp(),
                $reading->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Variação nas últimas $hours horas, para dizer se o rio sobe ou desce.
     * Devolve null quando o histórico é curto demais para afirmar tendência.
     *
     * @param  Collection<int, Reading>  $readings
     * @return array{value: float, hours: float}|null
     */
    private function change(Collection $readings, int $hours): ?array
    {
        $latest = $readings->last();

        if ($latest === null || $readings->count() < 2) {
            return null;
        }

        $target = $latest->measured_at->copy()->subHours($hours);

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
