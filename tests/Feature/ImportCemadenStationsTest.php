<?php

namespace Tests\Feature;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportCemadenStationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_station_with_its_three_reference_levels(): void
    {
        $this->fakeCemaden([
            $this->item('431600605H', 'Rio Rolante', 'ROLANTE', 'RS', atencao: 4.39, alerta: 5.85, transbordamento: 7.32),
        ]);

        $this->artisan('import:cemaden')->assertSuccessful();

        $station = Station::sole();

        $this->assertSame('cemaden', $station->source);
        $this->assertSame('431600605H', $station->external_id);
        $this->assertSame('Rio Rolante — ROLANTE', $station->name);
        $this->assertSame('Rio Rolante', $station->river);
        $this->assertSame(4.39, $station->attention_level);
        $this->assertSame(5.85, $station->alert_level);
        $this->assertSame(7.32, $station->critical_level);
    }

    /** O CEMADEN usa 0 como "não configurado" — tratar como cota real classificaria tudo como crítico. */
    public function test_it_treats_zero_reference_levels_as_not_configured(): void
    {
        $this->fakeCemaden([
            $this->item('432000814H', 'Arroio Jose Joaquim', 'SAPUCAIA DO SUL', 'RS', atencao: 0, alerta: 0, transbordamento: 0),
        ]);

        $this->artisan('import:cemaden')->assertSuccessful();

        $station = Station::sole();

        $this->assertNull($station->attention_level);
        $this->assertNull($station->alert_level);
        $this->assertNull($station->critical_level);
    }

    public function test_it_skips_stations_outside_rs(): void
    {
        $this->fakeCemaden([
            $this->item('260590518H', 'Centro', 'GAMELEIRA', 'PE', atencao: 1, alerta: 2, transbordamento: 3),
        ]);

        $this->artisan('import:cemaden')->assertSuccessful();

        $this->assertSame(0, Station::count());
    }

    public function test_it_skips_non_hydrological_station_types(): void
    {
        $item = $this->item('354990498H', 'Pluviômetro X', 'SÃO PAULO', 'SP', atencao: 1, alerta: 2, transbordamento: 3);
        $item['tipoestacao'] = 'Pluviométrica';

        $this->fakeCemaden([$item]);

        $this->artisan('import:cemaden')->assertSuccessful();

        $this->assertSame(0, Station::count());
    }

    public function test_it_does_not_duplicate_stations_on_reimport(): void
    {
        $this->fakeCemaden([
            $this->item('431600605H', 'Rio Rolante', 'ROLANTE', 'RS', atencao: 4.39, alerta: 5.85, transbordamento: 7.32),
        ]);

        $this->artisan('import:cemaden')->assertSuccessful();
        $this->artisan('import:cemaden')->assertSuccessful();

        $this->assertSame(1, Station::count());
    }

    /** A API do CEMADEN só devolve JSONP — sem o envelope, o corpo não é JSON válido. */
    public function test_it_fails_loudly_when_the_response_is_not_the_expected_jsonp_envelope(): void
    {
        Http::fake(['resources.cemaden.gov.br/*' => Http::response('<html>fora do ar</html>')]);

        $this->expectException(RuntimeException::class);

        $this->artisan('import:cemaden');
    }

    /** @param  list<array<string, mixed>>  $items */
    private function fakeCemaden(array $items): void
    {
        $body = 'estacoes('.json_encode([['estacao' => $items]]).')';

        Http::fake(['resources.cemaden.gov.br/*' => Http::response($body)]);
    }

    /** @return array<string, mixed> */
    private function item(
        string $codestacao,
        string $nomeestacao,
        string $cidade,
        string $uf,
        int|float $atencao,
        int|float $alerta,
        int|float $transbordamento,
    ): array {
        return [
            'codestacao' => $codestacao,
            'nomeestacao' => $nomeestacao,
            'cidade' => $cidade,
            'uf' => $uf,
            'latitude' => -29.6653,
            'longitude' => -50.5819,
            'tipoestacao' => 'Hidrológica',
            'cotaatencao' => $atencao,
            'cotaalerta' => $alerta,
            'cotatransbordamento' => $transbordamento,
        ];
    }
}
