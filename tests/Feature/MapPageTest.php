<?php

namespace Tests\Feature;

use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_map(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Cheias RS')
            ->assertSee('id="map"', escape: false);
    }

    public function test_it_publishes_each_station_with_its_coordinates_and_status(): void
    {
        $station = Station::create([
            'source' => 'sigdc',
            'external_id' => 'MON-GM-G01-LEVEL',
            'name' => 'Prainha do Itai',
            'municipality' => 'Eldorado do Sul',
            'latitude' => -30.006414,
            'longitude' => -51.301534,
            'alert_level' => 0.9,
            'critical_level' => 1.3,
            'unit' => 'm',
        ]);

        $station->readings()->create([
            'value' => 1.35,
            'measured_at' => now(),
            'source' => 'sigdc',
        ]);

        $response = $this->get('/');

        $response->assertOk();

        $stations = $response->viewData('stations');

        $this->assertCount(1, $stations);
        $this->assertSame('critical', $stations[0]['status']);
        $this->assertSame(1.35, $stations[0]['reading']['value']);
        $this->assertSame(-30.006414, $stations[0]['latitude']);
    }

    /**
     * Estação catalogada sem medição não vai ao mapa: um pin que não mede nada
     * só disputa atenção com os que medem.
     */
    public function test_it_omits_stations_that_have_no_reading(): void
    {
        Station::create([
            'source' => 'snirh',
            'external_id' => '86470000',
            'name' => 'ENCANTADO',
            'river' => 'RIO TAQUARI',
            'latitude' => -29.2344,
            'longitude' => -51.8550,
            'unit' => 'm',
        ]);

        $this->assertCount(0, $this->get('/')->viewData('stations'));
    }
}
