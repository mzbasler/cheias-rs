<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportSaceReadingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_measured_levels_converting_centimetres_to_metres(): void
    {
        $this->fakeSace(csv: "data_hora_medicao;indice\n2026-07-29 14:45:00;532.00\n");

        $this->artisan('import:sace')->assertSuccessful();

        $reading = Station::sole()->latestReading;

        $this->assertSame(5.32, $reading->value);
        $this->assertSame('sace', $reading->source);
    }

    /**
     * O SACE publica em hora de Brasília. Lido como UTC, todo nível entraria 3 h
     * no passado e o mapa marcaria estação ativa como sensor parado — escondendo
     * exatamente a cheia que precisa aparecer.
     */
    public function test_it_reads_timestamps_as_brasilia_time(): void
    {
        $this->fakeSace(csv: "data_hora_medicao;indice\n2026-07-29 14:45:00;532.00\n");

        $this->artisan('import:sace')->assertSuccessful();

        $this->assertSame(
            '2026-07-29T17:45:00+00:00',
            Station::sole()->latestReading->measured_at->toIso8601String(),
        );
    }

    public function test_it_imports_the_three_official_reference_levels(): void
    {
        $this->fakeSace();

        $this->artisan('import:sace')->assertSuccessful();

        $station = Station::sole();

        $this->assertSame(5.0, $station->attention_level);
        $this->assertSame(7.0, $station->alert_level);
        $this->assertSame(10.5, $station->critical_level);
    }

    /**
     * A cota de estação já catalogada pela ANA vem de import:ana (API oficial)
     * — duplicar a leitura aqui por raspagem seria a mesma estação com duas
     * fontes de verdade concorrendo pelo mesmo instante.
     */
    public function test_it_does_not_duplicate_readings_on_a_station_already_catalogued_by_ana(): void
    {
        $existing = Station::create([
            'source' => 'snirh',
            'external_id' => '86720000',
            'name' => 'ENCANTADO',
            'river' => 'RIO TAQUARI',
            'latitude' => -29.2344,
            'longitude' => -51.8550,
            'unit' => 'm',
        ]);

        $this->fakeSace(code: '8672000');

        $this->artisan('import:sace')->assertSuccessful();

        $this->assertSame(1, Station::count());
        $this->assertSame(0, Reading::count());
        // O nome do inventário prevalece sobre o rótulo do SACE.
        $this->assertSame('ENCANTADO', $existing->fresh()->name);
    }

    /** A ANA não publica cota de referência — isso continua vindo só do SACE. */
    public function test_it_still_fetches_reference_levels_for_a_station_catalogued_by_ana(): void
    {
        Station::create([
            'source' => 'snirh',
            'external_id' => '86720000',
            'name' => 'ENCANTADO',
            'latitude' => -29.2344,
            'longitude' => -51.8550,
            'unit' => 'm',
        ]);

        $this->fakeSace(code: '8672000');

        $this->artisan('import:sace')->assertSuccessful();

        $this->assertSame(5.0, Station::sole()->attention_level);
    }

    public function test_it_creates_a_station_when_the_code_is_not_in_the_inventory(): void
    {
        $this->fakeSace(code: '9999999');

        $this->artisan('import:sace')->assertSuccessful();

        $station = Station::sole();

        $this->assertSame('sace', $station->source);
        $this->assertSame(1, $station->readings()->count());
    }

    /** Estação que só mede chuva não tem CSV de cota — é ausência, não falha. */
    public function test_it_skips_stations_without_a_level_series(): void
    {
        $this->fakeSace(csvStatus: 404);

        $this->artisan('import:sace')->assertSuccessful();

        $this->assertSame(0, Reading::count());
    }

    /**
     * Não há API: os dados saem do HTML. Se o layout mudar, o comando precisa
     * gritar — em silêncio, o mapa congelaria sem ninguém perceber.
     */
    public function test_it_fails_loudly_when_the_page_layout_changes(): void
    {
        Http::fake(['*estacoes_mapa*' => Http::response('<html>sem marcadores</html>')]);

        $this->expectException(RuntimeException::class);

        $this->artisan('import:sace');
    }

    public function test_it_discards_rows_without_a_numeric_level(): void
    {
        $this->fakeSace(csv: "data_hora_medicao;indice\n2026-07-29 14:45:00;\n2026-07-29 15:00:00;532.00\n");

        $this->artisan('import:sace')->assertSuccessful();

        $this->assertSame(1, Reading::count());
    }

    public function test_it_does_not_duplicate_readings_on_reimport(): void
    {
        $this->fakeSace();

        $this->artisan('import:sace')->assertSuccessful();
        $this->artisan('import:sace')->assertSuccessful();

        $this->assertSame(1, Reading::count());
    }

    private function fakeSace(
        string $code = '8672000',
        ?string $csv = null,
        int $csvStatus = 200,
    ): void {
        Http::fake([
            '*estacoes_mapa*' => Http::response($this->basinPage($code)),
            '*relatorio.php*' => Http::response($this->report()),
            '*_cota.csv' => Http::response(
                $csv ?? "data_hora_medicao;indice\n2026-07-29 14:45:00;532.00\n",
                $csvStatus,
            ),
        ]);
    }

    /** Uma bacia com uma estação, no formato que o SACE renderiza. */
    private function basinPage(string $code): string
    {
        return <<<HTML
        <script>
            const popupContenttaquari_pm33 = `
                <iframe src="relatorio.php?apenas_grafico=sim&bacia=taquari&pm=33&s=56&sr=57"></iframe>
            `;
            const estacaotaquari_pm33 = L.marker([-29.23519, -51.85507], {icon: CotaDeAlerta})
                .addTo(grupoEstacao)
                .bindTooltip("{$code} - Encantado - WEB", {permanent: false});
        </script>
        HTML;
    }

    private function report(): string
    {
        return <<<'HTML'
        <script>
            { label: 'Cota de Atenção', data: Array.from({ length: 3 }, () => 500.000) },
            { label: 'Cota de Alerta', data: Array.from({ length: 3 }, () => 700.000) },
            { label: 'Cota de Inundação', data: Array.from({ length: 3 }, () => 1050.000) },
        </script>
        HTML;
    }
}
