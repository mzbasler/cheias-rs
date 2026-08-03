<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reading;
use App\Models\Station;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /** SIGDC, ANA e SACE alimentam readings.measured_at diretamente — dá para
     *  medir frescor por leitura. SNIRH só toca o catálogo (sem leitura), à
     *  parte. */
    private const READING_SOURCES = [
        'sigdc' => 'Defesa Civil (SIGDC)',
        'ana' => 'ANA (Hidroweb)',
        'sace' => 'SGB/CPRM (SACE) — sem correspondência na ANA',
    ];

    public function index(): View
    {
        $snirhLastImportedAt = Station::where('source', 'snirh')->max('created_at');

        return view('admin.dashboard', [
            'ingestion' => collect(self::READING_SOURCES)
                ->map(fn (string $label, string $source): array => [
                    'label' => $label,
                    ...$this->readingHealth($source),
                ]),
            'snirhLastImportedAt' => $snirhLastImportedAt ? Carbon::parse($snirhLastImportedAt) : null,
        ]);
    }

    /**
     * Por readings.source, não stations.source: import:ana não cria estação
     * própria, só enriquece a que a ANA mesma já catalogou (source=snirh) —
     * agrupar pela estação erraria o total (ficaria sempre zero).
     *
     * Estação por estação, não um MAX(measured_at) agregado: um agregado
     * ficaria "verde" com dezenas de estações desatualizadas atrás da primeira
     * que ainda respondeu.
     *
     * @return array{total: int, fresh: int}
     */
    private function readingHealth(string $source): array
    {
        $stationIds = Reading::where('source', $source)->distinct()->pluck('station_id');

        $stations = Station::with('latestReading')->whereIn('id', $stationIds)->get();

        return [
            'total' => $stations->count(),
            'fresh' => $stations->filter(fn (Station $station): bool => ! $station->latestReading->isStale())->count(),
        ];
    }
}
