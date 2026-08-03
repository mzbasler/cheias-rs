<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Models\Station;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Substitui a raspagem do SACE: a ANA publica a mesma cota (nível do rio) por
 * API oficial autenticada (Hidroweb Service). Reautentica a cada execução —
 * o token dura 1h, o ciclo é de minutos, não vale a complexidade de cachear.
 */
#[Signature('import:ana')]
#[Description('Importa níveis medidos das estações telemétricas da ANA (Hidroweb Service)')]
class ImportAnaReadings extends Command
{
    private const SOURCE = 'ana';

    private const BASE = 'https://www.ana.gov.br/hidrowebservice/EstacoesTelemetricas';

    /** Máximo de códigos por chamada — limite documentado da API. */
    private const BATCH_SIZE = 10;

    /** A ANA publica em hora de Brasília; o banco guarda em UTC. */
    private const TIMEZONE = 'America/Sao_Paulo';

    /** Seis colunas por linha, dentro do limite de variáveis do SQLite. */
    private const UPSERT_CHUNK = 120;

    public function handle(): int
    {
        $token = $this->authenticate();

        // As estações já vêm do inventário da ANA (import:snirh) — a ANA mede o
        // mesmo código que ela mesma catalogou, não há descoberta de estação
        // nova aqui, só leitura mais fresca para quem já existe.
        $stations = Station::where('source', 'snirh')->get()->keyBy('external_id');

        $readings = 0;

        foreach ($stations->keys()->chunk(self::BATCH_SIZE) as $batch) {
            $readings += $this->syncReadings($token, $stations, $batch->all());
        }

        $this->info("{$readings} leituras importadas da ANA.");

        return self::SUCCESS;
    }

    private function authenticate(): string
    {
        // O serviço da ANA é instável (timeout e 504 observados em teste manual) —
        // duas tentativas absorvem um engasgo sem exigir esperar o próximo ciclo.
        $body = Http::timeout(60)->retry(2, 2000)
            ->withHeaders([
                'Identificador' => config('services.ana.identificador'),
                'Senha' => config('services.ana.senha'),
            ])
            ->get(self::BASE.'/OAUth/v1')
            ->throw()
            ->json();

        $token = $body['items']['tokenautenticacao'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('ANA não devolveu token de autenticação: '.($body['message'] ?? 'resposta vazia'));
        }

        return $token;
    }

    /**
     * @param  Collection<string, Station>  $stations
     * @param  list<string>  $codes
     */
    private function syncReadings(string $token, Collection $stations, array $codes): int
    {
        $body = Http::timeout(60)->retry(2, 2000)
            ->withToken($token)
            ->get(self::BASE.'/HidroinfoanaSerieTelemetricaAdotada/v2', [
                'Codigos_Estacoes' => implode(',', $codes),
                'Tipo Filtro Data' => 'DATA_LEITURA',
                'Range Intervalo de busca' => 'DIAS_2',
            ])
            ->throw()
            ->json();

        $now = CarbonImmutable::now();
        $rows = [];

        foreach ($body['items'] ?? [] as $item) {
            // Estação sem escala reporta só chuva — ausência de cota, não zero.
            if (! is_numeric($item['Cota_Adotada'] ?? null)) {
                continue;
            }

            $station = $stations[$item['codigoestacao']] ?? null;

            if ($station === null) {
                continue;
            }

            // A ANA às vezes republica o mesmo instante duas vezes na mesma
            // resposta (revisão de valor). No Postgres, duas linhas com a mesma
            // chave de conflito no mesmo upsert é erro (SQLite deixava passar) —
            // a chave aqui garante uma linha por estação+instante, ficando com a
            // última (mais recente) publicada.
            $key = $station->id.':'.$item['Data_Hora_Medicao'];

            $rows[$key] = [
                'station_id' => $station->id,
                'measured_at' => CarbonImmutable::parse($item['Data_Hora_Medicao'], self::TIMEZONE)->utc(),
                'value' => ((float) $item['Cota_Adotada']) / 100, // A ANA publica cota em centímetros.
                'source' => self::SOURCE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $rows = array_values($rows);

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            Reading::upsert($chunk, ['station_id', 'measured_at'], ['value', 'source', 'updated_at']);
        }

        return count($rows);
    }
}
