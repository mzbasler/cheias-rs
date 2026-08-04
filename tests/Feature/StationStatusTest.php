<?php

namespace Tests\Feature;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_station_without_reading_is_unknown(): void
    {
        $station = $this->station();

        $this->assertSame('unknown', $station->status());
    }

    public function test_reading_below_every_reference_level_is_normal(): void
    {
        $station = $this->stationWithReading(1.50);

        $this->assertSame('normal', $station->status());
    }

    /**
     * O SGB publica "atenção" abaixo de "alerta". Como o mapa trata as duas como
     * o mesmo aviso, vale o limiar mais baixo — avisar antes é mais seguro.
     */
    public function test_alert_starts_at_the_lowest_published_threshold(): void
    {
        $station = $this->stationWithReading(1.80);

        $this->assertSame('alert', $station->status());
    }

    public function test_reading_at_alert_level_is_alert(): void
    {
        $station = $this->stationWithReading(2.15);

        $this->assertSame('alert', $station->status());
    }

    public function test_reading_at_critical_level_is_critical(): void
    {
        $station = $this->stationWithReading(2.50);

        $this->assertSame('critical', $station->status());
    }

    /**
     * Sensor mudo durante cheia não pode aparecer como "normal": o rio pode ter
     * subido desde a última leitura.
     */
    public function test_reading_older_than_the_stale_window_is_unknown(): void
    {
        $station = $this->stationWithReading(
            0.10,
            now()->subHours(Station::STALE_AFTER_HOURS + 1),
        );

        $this->assertSame('unknown', $station->status());
    }

    /**
     * Sem cota publicada não há como afirmar que o nível está normal — mas a
     * leitura em si existe e é fresca, então não é o mesmo problema de sensor
     * mudo ('unknown'): é 'unclassified', estação sem cota de referência.
     */
    public function test_fresh_reading_without_reference_levels_is_unclassified(): void
    {
        $station = $this->station([
            'attention_level' => null,
            'alert_level' => null,
            'critical_level' => null,
        ]);
        $station->readings()->create([
            'value' => 1.0,
            'measured_at' => now(),
            'source' => 'test',
        ]);

        $this->assertSame('unclassified', $station->fresh()->status());
    }

    /** @param  array<string, mixed>  $attributes */
    private function station(array $attributes = []): Station
    {
        return Station::create([
            'source' => 'test',
            'external_id' => uniqid(),
            'name' => 'Estação de teste',
            'latitude' => -30.0,
            'longitude' => -51.2,
            'attention_level' => 1.80,
            'alert_level' => 2.15,
            'critical_level' => 2.50,
            'unit' => 'm',
            ...$attributes,
        ]);
    }

    private function stationWithReading(float $value, ?\DateTimeInterface $measuredAt = null): Station
    {
        $station = $this->station();

        $station->readings()->create([
            'value' => $value,
            'measured_at' => $measuredAt ?? now(),
            'source' => 'test',
        ]);

        return $station->fresh();
    }
}
