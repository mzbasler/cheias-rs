<?php

namespace App\Console\Commands;

use App\Models\Station;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

#[Signature('import:snirh')]
#[Description('Importa a localização das estações fluviométricas do RS do inventário da ANA/SNIRH')]
class ImportSnirhStations extends Command
{
    private const SOURCE = 'snirh';

    private const ENDPOINT = 'https://www.snirh.gov.br/arcgis/rest/services/Hidroweb_BH/INVENTARIOS_ESTACOES/MapServer/0/query';

    /** Estações de rio, telemétricas e em operação, no RS. */
    private const WHERE = "UFD_SG='RS' AND TP_ESTACAO LIKE '%TELEM%' AND OPERANDO='Sim' AND RIO_NM IS NOT NULL";

    private const PAGE_SIZE = 500;

    public function handle(): int
    {
        $imported = 0;
        $skipped = 0;
        $offset = 0;

        do {
            $page = $this->fetchPage($offset);

            foreach ($page['features'] ?? [] as $feature) {
                $station = $this->toStation($feature);

                // Registro sem coordenada não vira pin: um pin no lugar errado,
                // durante cheia, é pior que pin nenhum.
                if ($station === null) {
                    $skipped++;

                    continue;
                }

                Station::updateOrCreate(
                    ['source' => self::SOURCE, 'external_id' => $station['external_id']],
                    $station,
                );

                $imported++;
            }

            $offset += self::PAGE_SIZE;
        } while ($page['exceededTransferLimit'] ?? false);

        $this->info("{$imported} estações importadas do SNIRH.");

        if ($skipped > 0) {
            $this->warn("{$skipped} ignoradas por não trazerem coordenada.");
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function fetchPage(int $offset): array
    {
        $response = Http::timeout(60)->get(self::ENDPOINT, [
            'where' => self::WHERE,
            'outFields' => 'EST_CD_FLU,EST_NM,MUN_NM,RIO_NM',
            'returnGeometry' => 'true',
            'outSR' => 4326, // O inventário é SAD69; pedimos WGS84, que é o do mapa.
            'resultOffset' => $offset,
            'resultRecordCount' => self::PAGE_SIZE,
            'f' => 'json',
        ])->throw();

        $body = $response->json();

        // O ArcGIS devolve HTTP 200 com corpo de erro — sem isto, a falha passaria calada.
        if (isset($body['error'])) {
            throw new RuntimeException('SNIRH recusou a consulta: '.json_encode($body['error']));
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<string, mixed>|null
     */
    private function toStation(array $feature): ?array
    {
        $latitude = $feature['geometry']['y'] ?? null;
        $longitude = $feature['geometry']['x'] ?? null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        $attributes = $feature['attributes'] ?? [];
        $code = $attributes['EST_CD_FLU'] ?? null;
        $name = trim((string) ($attributes['EST_NM'] ?? ''));

        return [
            'source' => self::SOURCE,
            // Sem código de estação, a coordenada identifica o ponto de forma estável.
            'external_id' => (string) ($code ?: sprintf('%.5f,%.5f', $latitude, $longitude)),
            'name' => $name !== '' ? $name : 'Estação sem nome',
            'river' => $attributes['RIO_NM'] ?? null,
            'municipality' => $attributes['MUN_NM'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'unit' => 'm',
        ];
    }
}
