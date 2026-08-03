<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Models\Station;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A cota de estação catalogada pela ANA vem de import:ana (API oficial) — este
 * comando cobre só o que a ANA não tem: as estações que o SACE mede mas que não
 * existem no catálogo dela (código próprio do SACE, tipo "cai:108"). Ainda
 * assim busca as três cotas de referência de qualquer estação sem elas, porque
 * a ANA não publica esse dado.
 *
 * O SACE não publica API: os dados vêm da própria página do mapa de níveis, que
 * traz os marcadores já renderizados, e de um CSV por estação. É raspagem, então
 * qualquer mudança de layout precisa falhar alto — em silêncio, o mapa
 * simplesmente pararia de atualizar sem ninguém notar.
 */
#[Signature('import:sace {--metadata : Rebusca cotas de referência de todas as estações}')]
#[Description('Importa cotas de referência e níveis das estações do SACE sem correspondência na ANA')]
class ImportSaceReadings extends Command
{
    private const SOURCE = 'sace';

    private const BASE = 'https://www.sgb.gov.br/sace/sace_nivel/';

    /** Bacias monitoradas que cobrem o RS. */
    private const BASINS = ['taquari', 'uruguai', 'guaiba', 'cai'];

    /** O SACE publica em hora de Brasília; o banco guarda em UTC. */
    private const TIMEZONE = 'America/Sao_Paulo';

    /** Código de estação da ANA tem 8 dígitos; o SACE publica sem os zeros finais. */
    private const ANA_CODE_LENGTH = 8;

    /** Seis colunas por linha, dentro do limite de variáveis do SQLite. */
    private const UPSERT_CHUNK = 120;

    /** As páginas de bacia se sobrepõem (Guaíba lista estações do Caí, por exemplo). */
    private array $seen = [];

    public function handle(): int
    {
        $stations = 0;
        $orphans = 0;
        $readings = 0;

        foreach (self::BASINS as $basin) {
            foreach ($this->parseStations($basin) as $parsed) {
                $key = "{$parsed['basin']}:{$parsed['pm']}";

                if (isset($this->seen[$key])) {
                    continue;
                }

                $this->seen[$key] = true;

                $station = $this->syncStation($key, $parsed);
                $stations++;

                // Cota de estação já catalogada pela ANA vem de import:ana — aqui
                // só completa cota de referência, nunca duplica a leitura.
                if ($station->source !== self::SOURCE) {
                    continue;
                }

                $orphans++;
                $readings += $this->syncReadings($station, $parsed);
            }
        }

        $this->info("{$stations} estações vistas, {$orphans} sem correspondência na ANA, {$readings} leituras importadas do SACE.");

        return self::SUCCESS;
    }

    /**
     * Extrai os marcadores que a página do mapa já traz renderizados.
     *
     * @return list<array<string, mixed>>
     */
    private function parseStations(string $basin): array
    {
        $html = Http::timeout(60)->get(self::BASE.'estacoes_mapa.php', ['bacia' => $basin])->throw()->body();

        $matched = preg_match_all(
            '/src="relatorio\.php\?apenas_grafico=sim&bacia=(?<basin>\w+)&pm=(?<pm>\d+)&s=(?<s>\d*)&sr=(?<sr>\d*)"'
            .'[\s\S]{0,600}?L\.marker\(\[\s*(?<lat>-?[\d.]+),\s*(?<lon>-?[\d.]+)\s*\][\s\S]{0,400}?'
            .'bindTooltip\("(?<label>[^"]+)"/u',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        if ($matched === 0) {
            throw new RuntimeException(
                "Nenhuma estação extraída da bacia {$basin}. A página do SACE provavelmente mudou de formato."
            );
        }

        return array_map(fn (array $m): array => [
            'basin' => $m['basin'],
            'pm' => $m['pm'],
            'query' => ['bacia' => $m['basin'], 'pm' => $m['pm'], 's' => $m['s'], 'sr' => $m['sr']],
            'latitude' => (float) $m['lat'],
            'longitude' => (float) $m['lon'],
            ...$this->splitLabel($m['label']),
        ], $matches);
    }

    /** O rótulo vem como "8672000 - Encantado" ou "871700 - Barca do Caí - WEB". */
    private function splitLabel(string $label): array
    {
        $parts = array_map(trim(...), explode(' - ', html_entity_decode($label)));
        $code = array_shift($parts);

        // O sufixo "WEB" indica o meio de transmissão, não faz parte do nome.
        $parts = array_filter($parts, fn (string $part): bool => strcasecmp($part, 'WEB') !== 0);

        return ['code' => $code, 'name' => implode(' - ', $parts) ?: $code];
    }

    /**
     * O SACE mede as mesmas estações que o inventário da ANA já catalogou. Quando
     * o código coincide, a leitura entra na estação existente em vez de criar uma
     * duplicata a poucos metros dela no mapa.
     *
     * @param  array<string, mixed>  $parsed
     */
    private function syncStation(string $key, array $parsed): Station
    {
        $station = Station::firstWhere([
            'source' => 'snirh',
            'external_id' => str_pad($parsed['code'], self::ANA_CODE_LENGTH, '0'),
        ]) ?? Station::firstOrNew(['source' => self::SOURCE, 'external_id' => $key]);

        // Nome e coordenada do inventário da ANA são a referência; do SACE, só
        // preenchem o que falta.
        $station->fill([
            'name' => $station->name ?? $parsed['name'],
            'latitude' => $station->latitude ?? $parsed['latitude'],
            'longitude' => $station->longitude ?? $parsed['longitude'],
            'unit' => 'm',
        ]);

        // As cotas de referência mudam raramente: busca só na primeira vez, para
        // não repetir uma requisição por estação a cada ciclo.
        if ($this->option('metadata') || $station->attention_level === null) {
            $station->fill($this->fetchReferenceLevels($parsed['query']));
        }

        $station->save();

        return $station;
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, float>
     */
    private function fetchReferenceLevels(array $query): array
    {
        $html = Http::timeout(60)->get(self::BASE.'relatorio.php', $query)->throw()->body();

        preg_match_all(
            '/label:\s*\x27Cota de (?<name>[^\x27]+)\x27[\s\S]{0,400}?=>\s*(?<value>[\d.]+)/u',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $column = [
            'atenção' => 'attention_level',
            'alerta' => 'alert_level',
            'inundação' => 'critical_level',
        ];

        $levels = [];

        foreach ($matches as $match) {
            $key = $column[mb_strtolower(trim($match['name']))] ?? null;

            if ($key !== null) {
                $levels[$key] = ((float) $match['value']) / 100; // O SACE publica em centímetros.
            }
        }

        return $levels;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return int quantas leituras foram gravadas
     */
    private function syncReadings(Station $station, array $parsed): int
    {
        $response = Http::timeout(60)
            ->get(self::BASE."api/dados/{$parsed['basin']}_{$parsed['pm']}_cota.csv");

        // 404 aqui significa estação que não mede nível (pluviométrica), não falha.
        // Qualquer outro erro é problema de verdade e precisa estourar.
        if ($response->status() === 404) {
            return 0;
        }

        $lines = array_slice(preg_split('/\R/', trim($response->throw()->body())), 1); // Descarta o cabeçalho.
        $now = CarbonImmutable::now();
        $rows = [];

        foreach ($lines as $line) {
            [$measuredAt, $centimetres] = array_pad(explode(';', trim($line)), 2, null);

            // Linha truncada ou sem valor é lacuna do sensor, não leitura zero.
            if (! is_numeric($centimetres) || $measuredAt === null || $measuredAt === '') {
                continue;
            }

            // Linha repetida no CSV é a mesma chave de conflito duas vezes no
            // mesmo upsert — Postgres rejeita (SQLite deixava passar); fica com
            // a última.
            $rows[$measuredAt] = [
                'station_id' => $station->id,
                // `upsert` não passa pelos casts do model: a conversão para UTC
                // tem de ser explícita, senão o horário entra com o fuso embutido.
                'measured_at' => CarbonImmutable::parse($measuredAt, self::TIMEZONE)->utc(),
                'value' => ((float) $centimetres) / 100,
                'source' => self::SOURCE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $rows = array_values($rows);

        // Cada CSV traz ~21 dias a cada 15 min. Gravar linha por linha custava duas
        // mil consultas por estação; em lote são poucas.
        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            Reading::upsert($chunk, ['station_id', 'measured_at'], ['value', 'source', 'updated_at']);
        }

        return count($rows);
    }
}
