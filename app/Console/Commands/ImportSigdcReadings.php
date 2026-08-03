<?php

namespace App\Console\Commands;

use App\Models\Station;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('import:sigdc')]
#[Description('Importa os pontos e as leituras de nível da Defesa Civil (SIGDC)')]
class ImportSigdcReadings extends Command
{
    private const SOURCE = 'sigdc';

    private const ENDPOINT = 'https://unigov.com.br/defesacivil/api/monitoring/points';

    public function handle(): int
    {
        $points = Http::timeout(30)
            ->get(self::ENDPOINT)
            ->throw()
            ->json();

        $stations = 0;
        $readings = 0;

        foreach ($points as $point) {
            if (($point['metricClass'] ?? null) !== 'water_level') {
                continue;
            }

            $station = $this->syncStation($point);

            if ($station === null) {
                continue;
            }

            $stations++;
            $readings += $this->syncReadings($station, $point);
        }

        $this->info("{$stations} pontos e {$readings} leituras importados do SIGDC.");

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $point */
    private function syncStation(array $point): ?Station
    {
        $latitude = $point['lat'] ?? null;
        $longitude = $point['lng'] ?? null;

        // Sem coordenada não há pin: melhor a estação ausente do que no lugar errado.
        if ($latitude === null || $longitude === null) {
            $this->warn("Ponto {$point['id']} ignorado: sem coordenada.");

            return null;
        }

        return Station::updateOrCreate(
            ['source' => self::SOURCE, 'external_id' => $point['id']],
            [
                'name' => $point['location'] ?? $point['name'],
                'municipality' => 'Eldorado do Sul',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'alert_level' => $point['alertValue'] ?? null,
                'critical_level' => $point['criticalValue'] ?? null,
                'unit' => $point['unit'] ?? 'm',
            ],
        );
    }

    /**
     * Grava a leitura atual e as 48 h de histórico que vêm na mesma resposta.
     *
     * @param  array<string, mixed>  $point
     * @return int quantas leituras foram gravadas
     */
    private function syncReadings(Station $station, array $point): int
    {
        $samples = collect($point['history'] ?? [])
            ->map(fn (array $entry): array => [
                'value' => $entry['value'] ?? null,
                'measured_at' => $entry['timestamp'] ?? null,
                'trend' => null,
            ])
            ->push([
                'value' => $point['currentValue'] ?? null,
                'measured_at' => $point['lastReadingAt'] ?? null,
                'trend' => $point['trend'] ?? null,
            ])
            // Amostra sem valor ou sem instante é descartada: não se inventa a
            // medição nem se carimba a leitura com a hora de agora.
            ->filter(fn (array $sample): bool => $sample['value'] !== null && $sample['measured_at'] !== null);

        foreach ($samples as $sample) {
            $station->readings()->updateOrCreate(
                ['measured_at' => CarbonImmutable::parse($sample['measured_at'])],
                [
                    'value' => $sample['value'],
                    'trend' => $sample['trend'],
                    'source' => self::SOURCE,
                ],
            );
        }

        return $samples->count();
    }
}
