<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportSigdcReadingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_water_level_points_with_their_reference_levels(): void
    {
        Http::fake(['*' => Http::response([$this->point()])]);

        $this->artisan('import:sigdc')->assertSuccessful();

        $station = Station::sole();

        $this->assertSame('sigdc', $station->source);
        $this->assertSame('MON-GM-G01-LEVEL', $station->external_id);
        $this->assertSame('Prainha do Itai', $station->name);
        $this->assertSame(0.9, $station->alert_level);
        $this->assertSame(1.3, $station->critical_level);
    }

    public function test_it_stores_the_current_reading_with_the_instant_it_was_measured(): void
    {
        Http::fake(['*' => Http::response([$this->point()])]);

        $this->artisan('import:sigdc')->assertSuccessful();

        $reading = Station::sole()->latestReading;

        $this->assertSame(0.81, $reading->value);
        $this->assertSame('2026-07-29T17:53:20+00:00', $reading->measured_at->toIso8601String());
        $this->assertSame('falling', $reading->trend);
    }

    public function test_it_stores_the_history_that_comes_with_the_point(): void
    {
        Http::fake(['*' => Http::response([$this->point([
            'history' => [
                ['value' => 0.65, 'timestamp' => '2026-07-27T09:47:47+00:00'],
                ['value' => 0.72, 'timestamp' => '2026-07-28T09:47:47+00:00'],
            ],
        ])])]);

        $this->artisan('import:sigdc')->assertSuccessful();

        $this->assertSame(3, Reading::count());
    }

    /**
     * Amostra sem valor ou sem instante seria uma medição inventada — ou uma
     * leitura carimbada com a hora de agora, que é pior ainda.
     */
    public function test_it_discards_samples_without_value_or_timestamp(): void
    {
        Http::fake(['*' => Http::response([$this->point([
            'history' => [
                ['value' => null, 'timestamp' => '2026-07-27T09:47:47+00:00'],
                ['value' => 0.72, 'timestamp' => null],
            ],
        ])])]);

        $this->artisan('import:sigdc')->assertSuccessful();

        $this->assertSame(1, Reading::count());
    }

    public function test_it_ignores_points_that_do_not_measure_water_level(): void
    {
        Http::fake(['*' => Http::response([
            $this->point(['metricClass' => 'rainfall']),
        ])]);

        $this->artisan('import:sigdc')->assertSuccessful();

        $this->assertSame(0, Station::count());
    }

    public function test_it_skips_points_without_coordinates(): void
    {
        Http::fake(['*' => Http::response([
            $this->point(['lat' => null, 'lng' => null]),
        ])]);

        $this->artisan('import:sigdc')->assertSuccessful();

        $this->assertSame(0, Station::count());
    }

    public function test_it_does_not_duplicate_readings_on_reimport(): void
    {
        Http::fake(['*' => Http::response([$this->point()])]);

        $this->artisan('import:sigdc')->assertSuccessful();
        $this->artisan('import:sigdc')->assertSuccessful();

        $this->assertSame(1, Station::count());
        $this->assertSame(1, Reading::count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function point(array $overrides = []): array
    {
        return [
            'id' => 'MON-GM-G01-LEVEL',
            'metricClass' => 'water_level',
            'location' => 'Prainha do Itai',
            'name' => 'G01 - Nível do Rio',
            'unit' => 'm',
            'alertValue' => 0.9,
            'criticalValue' => 1.3,
            'currentValue' => 0.81,
            'trend' => 'falling',
            'lastReadingAt' => '2026-07-29T17:53:20+00:00',
            'lat' => -30.006414,
            'lng' => -51.301534,
            'history' => [],
            ...$overrides,
        ];
    }
}
