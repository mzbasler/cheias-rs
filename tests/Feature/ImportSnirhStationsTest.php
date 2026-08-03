<?php

namespace Tests\Feature;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportSnirhStationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_stations_with_their_coordinates(): void
    {
        Http::fake([
            '*' => Http::response($this->page([
                $this->feature('86470000', 'ENCANTADO', 'RIO TAQUARI', -29.2344, -51.8550),
            ])),
        ]);

        $this->artisan('import:snirh')->assertSuccessful();

        $station = Station::sole();

        $this->assertSame('snirh', $station->source);
        $this->assertSame('86470000', $station->external_id);
        $this->assertSame('ENCANTADO', $station->name);
        $this->assertSame('RIO TAQUARI', $station->river);
        $this->assertEqualsWithDelta(-29.2344, $station->latitude, 0.00001);
        $this->assertEqualsWithDelta(-51.8550, $station->longitude, 0.00001);
    }

    /**
     * Pin sem coordenada real seria pin no lugar errado — e num app de cheia isso
     * é pior do que estação ausente.
     */
    public function test_it_skips_features_without_geometry(): void
    {
        Http::fake([
            '*' => Http::response($this->page([
                ['attributes' => ['EST_CD_FLU' => '1', 'EST_NM' => 'SEM GEOMETRIA']],
                $this->feature('2', 'COM GEOMETRIA', 'RIO JACUÍ', -29.6275, -53.3533),
            ])),
        ]);

        $this->artisan('import:snirh')->assertSuccessful();

        $this->assertSame(1, Station::count());
        $this->assertSame('COM GEOMETRIA', Station::sole()->name);
    }

    public function test_it_follows_pagination_until_the_last_page(): void
    {
        Http::fakeSequence()
            ->push($this->page([$this->feature('1', 'PRIMEIRA', 'RIO A', -29.1, -51.1)], exceeded: true))
            ->push($this->page([$this->feature('2', 'SEGUNDA', 'RIO B', -29.2, -51.2)]));

        $this->artisan('import:snirh')->assertSuccessful();

        $this->assertSame(2, Station::count());
    }

    /**
     * O ArcGIS responde HTTP 200 mesmo quando recusa a consulta. Engolir isso
     * apagaria estações do mapa sem ninguém perceber.
     */
    public function test_it_fails_loudly_when_the_service_returns_an_error_body(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['code' => 400, 'message' => 'Invalid where clause']]),
        ]);

        $this->expectException(RuntimeException::class);

        $this->artisan('import:snirh');
    }

    public function test_it_does_not_duplicate_stations_on_reimport(): void
    {
        Http::fake([
            '*' => Http::response($this->page([
                $this->feature('86470000', 'ENCANTADO', 'RIO TAQUARI', -29.2344, -51.8550),
            ])),
        ]);

        $this->artisan('import:snirh')->assertSuccessful();
        $this->artisan('import:snirh')->assertSuccessful();

        $this->assertSame(1, Station::count());
    }

    /** @param  array<int, array<string, mixed>>  $features */
    private function page(array $features, bool $exceeded = false): array
    {
        return ['features' => $features, 'exceededTransferLimit' => $exceeded];
    }

    /** @return array<string, mixed> */
    private function feature(string $code, string $name, string $river, float $lat, float $lon): array
    {
        return [
            'attributes' => [
                'EST_CD_FLU' => $code,
                'EST_NM' => $name,
                'RIO_NM' => $river,
                'MUN_NM' => 'MUNICÍPIO',
            ],
            'geometry' => ['x' => $lon, 'y' => $lat],
        ];
    }
}
