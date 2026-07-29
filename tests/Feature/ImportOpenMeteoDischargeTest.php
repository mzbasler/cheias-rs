<?php

namespace Tests\Feature;

use App\Models\Reading;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportOpenMeteoDischargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_modeled_discharge_for_each_station(): void
    {
        $station = $this->station();

        Http::fake(['*' => Http::response($this->flood([
            '2026-07-29' => 5.85,
            '2026-07-30' => 7.97,
        ]))]);

        $this->artisan('import:discharge')->assertSuccessful();

        $this->assertSame(2, $station->readings()->count());
        $this->assertSame(5.85, $station->todayDischarge->value);
    }

    /**
     * Vazão modelada e nível medido são grandezas distintas: precisam conviver
     * sem que uma sobrescreva a outra.
     */
    public function test_modeled_discharge_does_not_replace_a_measured_level(): void
    {
        $station = $this->station();
        $station->readings()->create([
            'metric' => Reading::METRIC_LEVEL,
            'value' => 1.20,
            'measured_at' => now()->startOfDay(),
            'source' => 'sigdc',
        ]);

        Http::fake(['*' => Http::response($this->flood([now()->toDateString() => 400.0]))]);

        $this->artisan('import:discharge')->assertSuccessful();

        $this->assertSame(1.20, $station->latestReading->value);
        $this->assertSame(400.0, $station->fresh()->todayDischarge->value);
    }

    public function test_station_with_only_modeled_discharge_is_reported_as_modeled(): void
    {
        $station = $this->station();

        Http::fake(['*' => Http::response($this->flood([now()->toDateString() => 12.0]))]);

        $this->artisan('import:discharge')->assertSuccessful();

        $this->assertSame('modeled', $station->fresh()->status());
    }

    /** Dia sem valor no modelo é lacuna — gravar zero seria inventar rio seco. */
    public function test_it_skips_days_the_model_did_not_return(): void
    {
        $station = $this->station();

        Http::fake(['*' => Http::response($this->flood([
            '2026-07-29' => null,
            '2026-07-30' => 7.97,
        ]))]);

        $this->artisan('import:discharge')->assertSuccessful();

        $this->assertSame(1, $station->readings()->count());
    }

    public function test_it_does_not_duplicate_estimates_on_reimport(): void
    {
        $this->station();

        Http::fake(['*' => Http::response($this->flood(['2026-07-29' => 5.85]))]);

        $this->artisan('import:discharge')->assertSuccessful();
        $this->artisan('import:discharge')->assertSuccessful();

        $this->assertSame(1, Reading::count());
    }

    private function station(): Station
    {
        return Station::create([
            'source' => 'snirh',
            'external_id' => '86470000',
            'name' => 'ENCANTADO',
            'river' => 'RIO TAQUARI',
            'latitude' => -29.2344,
            'longitude' => -51.8550,
            'unit' => 'm',
        ]);
    }

    /**
     * @param  array<string, float|null>  $byDay
     * @return array<int, array<string, mixed>>
     */
    private function flood(array $byDay): array
    {
        return [[
            'latitude' => -29.2344,
            'longitude' => -51.8550,
            'daily' => [
                'time' => array_keys($byDay),
                'river_discharge' => array_values($byDay),
            ],
        ]];
    }
}
