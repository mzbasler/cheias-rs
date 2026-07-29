<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Models\Station;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

#[Signature('import:discharge')]
#[Description('Importa a vazão estimada (GloFAS/Open-Meteo) de cada estação mapeada')]
class ImportOpenMeteoDischarge extends Command
{
    private const SOURCE = 'open-meteo';

    private const ENDPOINT = 'https://flood-api.open-meteo.com/v1/flood';

    /** A API aceita várias coordenadas por chamada; 100 mantém a URL em tamanho seguro. */
    private const BATCH_SIZE = 100;

    public function handle(): int
    {
        $imported = 0;

        foreach (Station::orderBy('id')->get()->chunk(self::BATCH_SIZE) as $batch) {
            $imported += $this->importBatch($batch);
        }

        $this->info("{$imported} estimativas de vazão importadas.");

        return self::SUCCESS;
    }

    /** @param  Collection<int, Station>  $batch */
    private function importBatch(Collection $batch): int
    {
        $locations = $this->fetch($batch);
        $imported = 0;

        // A API devolve os locais na mesma ordem em que foram pedidos.
        foreach ($batch->values() as $index => $station) {
            $daily = $locations[$index]['daily'] ?? null;

            if ($daily === null) {
                continue;
            }

            $imported += $this->store($station, $daily);
        }

        return $imported;
    }

    /**
     * @param  Collection<int, Station>  $batch
     * @return array<int, array<string, mixed>>
     */
    private function fetch(Collection $batch): array
    {
        $response = Http::timeout(60)->get(self::ENDPOINT, [
            'latitude' => $batch->pluck('latitude')->implode(','),
            'longitude' => $batch->pluck('longitude')->implode(','),
            'daily' => 'river_discharge',
            'past_days' => 1,
            'forecast_days' => 3,
        ])->throw()->json();

        // Com uma única coordenada a API devolve o objeto solto, não uma lista.
        return array_is_list($response) ? $response : [$response];
    }

    /**
     * @param  array<string, mixed>  $daily
     * @return int quantas estimativas foram gravadas
     */
    private function store(Station $station, array $daily): int
    {
        $days = $daily['time'] ?? [];
        $discharges = $daily['river_discharge'] ?? [];
        $stored = 0;

        foreach ($days as $index => $day) {
            $value = $discharges[$index] ?? null;

            // Dia sem valor no modelo é lacuna, não zero.
            if ($value === null) {
                continue;
            }

            $station->readings()->updateOrCreate(
                [
                    'metric' => Reading::METRIC_DISCHARGE,
                    'measured_at' => CarbonImmutable::parse($day)->startOfDay(),
                ],
                ['value' => $value, 'source' => self::SOURCE],
            );

            $stored++;
        }

        return $stored;
    }
}
