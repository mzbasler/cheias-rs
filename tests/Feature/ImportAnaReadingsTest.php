<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportAnaReadingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_readings_converting_centimetres_to_metres(): void
    {
        $this->createStation();
        $this->fakeAna(items: [$this->item(cota: '278.00')]);

        $this->artisan('import:ana')->assertSuccessful();

        $reading = Station::sole()->latestReading;

        $this->assertSame(2.78, $reading->value);
        $this->assertSame('ana', $reading->source);
    }

    /**
     * A ANA publica em hora de Brasília. Lido como UTC, todo nível entraria 3 h
     * no passado e o mapa marcaria estação ativa como sensor parado — escondendo
     * exatamente a cheia que precisa aparecer.
     */
    public function test_it_reads_timestamps_as_brasilia_time(): void
    {
        $this->createStation();
        $this->fakeAna(items: [$this->item(medicao: '2026-08-03 00:00:00.0')]);

        $this->artisan('import:ana')->assertSuccessful();

        $this->assertSame(
            '2026-08-03T03:00:00+00:00',
            Station::sole()->latestReading->measured_at->toIso8601String(),
        );
    }

    /** Estação sem escala reporta chuva mas não cota — ausência, não leitura zero. */
    public function test_it_skips_entries_without_a_cota(): void
    {
        $this->createStation();
        $this->fakeAna(items: [$this->item(cota: null)]);

        $this->artisan('import:ana')->assertSuccessful();

        $this->assertSame(0, Reading::count());
    }

    public function test_it_does_not_duplicate_readings_on_reimport(): void
    {
        $this->createStation();
        $this->fakeAna(items: [$this->item()]);

        $this->artisan('import:ana')->assertSuccessful();
        $this->artisan('import:ana')->assertSuccessful();

        $this->assertSame(1, Reading::count());
    }

    public function test_it_does_nothing_when_no_station_is_catalogued_by_ana(): void
    {
        $this->fakeAna(items: [$this->item()]);

        $this->artisan('import:ana')->assertSuccessful();

        $this->assertSame(0, Reading::count());
    }

    /** Sem token, não há como consultar a série — a falha precisa ser visível. */
    public function test_it_fails_loudly_when_authentication_does_not_return_a_token(): void
    {
        Http::fake([
            '*OAUth/v1*' => Http::response(['status' => 'OK', 'code' => 200, 'message' => 'Sucesso', 'items' => []]),
        ]);

        $this->expectException(RuntimeException::class);

        $this->artisan('import:ana');
    }

    private function createStation(): Station
    {
        return Station::create([
            'source' => 'snirh',
            'external_id' => '86720000',
            'name' => 'ENCANTADO',
            'river' => 'RIO TAQUARI',
            'latitude' => -29.2344,
            'longitude' => -51.8550,
            'unit' => 'm',
        ]);
    }

    /** @param  list<array<string, mixed>>  $items */
    private function fakeAna(array $items): void
    {
        Http::fake([
            '*OAUth/v1*' => Http::response([
                'status' => 'OK',
                'code' => 200,
                'message' => 'Sucesso',
                'items' => ['sucesso' => true, 'tokenautenticacao' => 'fake-token'],
            ]),
            '*HidroinfoanaSerieTelemetricaAdotada*' => Http::response([
                'status' => 'OK',
                'code' => 200,
                'message' => 'Sucesso',
                'items' => $items,
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function item(string $codigo = '86720000', ?string $cota = '278.00', string $medicao = '2026-08-03 00:00:00.0'): array
    {
        return [
            'Chuva_Adotada' => '0.00',
            'Chuva_Adotada_Status' => '0',
            'Cota_Adotada' => $cota,
            'Cota_Adotada_Status' => '0',
            'Data_Atualizacao' => $medicao,
            'Data_Hora_Medicao' => $medicao,
            'Vazao_Adotada' => '985.67',
            'Vazao_Adotada_Status' => '0',
            'codigoestacao' => $codigo,
        ];
    }
}
