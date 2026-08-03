<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /** SIGDC e SACE alimentam readings.measured_at diretamente — dá para medir
     *  frescor por leitura. SNIRH só toca o catálogo (sem leitura), à parte. */
    private const READING_SOURCES = [
        'sigdc' => 'Defesa Civil (SIGDC)',
        'sace' => 'SGB/CPRM (SACE)',
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
     * Estação por estação, não um MAX(measured_at) agregado: o import do SACE
     * grava em loop e aborta no primeiro erro, então um agregado ficaria
     * "verde" com dezenas de estações desatualizadas atrás da primeira que
     * ainda respondeu.
     *
     * @return array{total: int, fresh: int}
     */
    private function readingHealth(string $source): array
    {
        $stations = Station::with('latestReading')
            ->where('source', $source)
            ->whereHas('readings')
            ->get();

        return [
            'total' => $stations->count(),
            'fresh' => $stations->filter(fn (Station $station): bool => ! $station->latestReading->isStale())->count(),
        ];
    }
}
