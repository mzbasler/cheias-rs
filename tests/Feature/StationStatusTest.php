<?php

namespace Tests\Feature;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Estação de catálogo é presença no mapa, não ocorrência: não pode dividir o
     * símbolo com um sensor que parou de reportar.
     */
    public function test_station_that_never_reported_is_unmonitored(): void
    {
        $station = $this->station();

        $this->assertSame('unmonitored', $station->status());
    }

    public function test_reading_below_alert_level_is_normal(): void
    {
        $station = $this->stationWithReading(2.00);

        $this->assertSame('normal', $station->status());
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
     * Sensor mudo durante cheia não pode aparecer como "normal" — o rio pode ter
     * subido desde a última leitura. E é distinto de nunca ter reportado: aqui há
     * um feed que parou, o que merece atenção.
     */
    public function test_reading_older_than_the_stale_window_is_stale_even_when_below_alert_level(): void
    {
        $station = $this->stationWithReading(
            0.10,
            now()->subHours(Station::STALE_AFTER_HOURS + 1),
        );

        $this->assertSame('stale', $station->status());
    }

    /**
     * Sem cota publicada não há como afirmar que o nível está normal — mas a
     * leitura existe e é recente, então também não é "sem monitoramento".
     */
    public function test_fresh_reading_without_reference_levels_is_unrated(): void
    {
        $station = $this->station(['alert_level' => null, 'critical_level' => null]);
        $station->readings()->create([
            'value' => 1.0,
            'measured_at' => now(),
            'source' => 'test',
        ]);

        $this->assertSame('unrated', $station->fresh()->status());
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
