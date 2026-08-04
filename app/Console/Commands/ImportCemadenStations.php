<?php

namespace App\Console\Commands;

use App\Models\Station;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * O CEMADEN publica cota de atenção/alerta/transbordamento por estação
 * hidrológica — a classificação de risco que a ANA não tem e que o SACE só
 * cobre em 4 bacias do RS.
 *
 * Só cota, sem leitura: a API também devolve um campo "nivel", mas sem
 * instante de medição e sem confirmação pública de que já desconta o offset
 * do sensor (a diferença entre os dois não bate com a cota em vários casos).
 * Importar isso arriscaria mostrar um valor errado como se fosse a leitura
 * atual do rio — melhor a estação ficar sem leitura do que com uma errada.
 */
#[Signature('import:cemaden')]
#[Description('Importa estações e cotas de referência das estações hidrológicas do CEMADEN no RS')]
class ImportCemadenStations extends Command
{
    private const SOURCE = 'cemaden';

    private const CATALOG_URL = 'https://resources.cemaden.gov.br/dados/327mi_24.json';

    public function handle(): int
    {
        $items = $this->fetchCatalog();

        $imported = 0;

        foreach ($items as $item) {
            if (($item['uf'] ?? null) !== 'RS' || ($item['tipoestacao'] ?? null) !== 'Hidrológica') {
                continue;
            }

            Station::updateOrCreate(
                ['source' => self::SOURCE, 'external_id' => $item['codestacao']],
                [
                    'name' => "{$item['nomeestacao']} — {$item['cidade']}",
                    'river' => $item['nomeestacao'],
                    'municipality' => $item['cidade'],
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                    // O CEMADEN usa 0 para "não configurado", não para cota real.
                    'attention_level' => $this->levelOrNull($item['cotaatencao'] ?? null),
                    'alert_level' => $this->levelOrNull($item['cotaalerta'] ?? null),
                    'critical_level' => $this->levelOrNull($item['cotatransbordamento'] ?? null),
                    'unit' => 'm',
                ],
            );

            $imported++;
        }

        $this->info("{$imported} estações importadas do CEMADEN.");

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function fetchCatalog(): array
    {
        $body = Http::timeout(60)->get(self::CATALOG_URL)->throw()->body();

        // A API devolve JSONP fixo (`estacoes([...])`), nunca JSON puro.
        if (! preg_match('/^estacoes\((.*)\);?\s*$/s', trim($body), $matches)) {
            throw new RuntimeException('CEMADEN devolveu formato inesperado — não veio no envelope estacoes(...).');
        }

        $decoded = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        return $decoded[0]['estacao'] ?? [];
    }

    private function levelOrNull(int|float|null $level): ?float
    {
        return $level > 0 ? (float) $level : null;
    }
}
