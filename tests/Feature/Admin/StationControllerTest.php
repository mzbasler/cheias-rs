<?php

namespace Tests\Feature\Admin;

use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_station_list(): void
    {
        $this->get('/admin/stations')->assertRedirect(route('login'));
    }

    public function test_index_lists_stations_without_any_reading(): void
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

        $response = $this->actingAs(User::factory()->create())->get('/admin/stations');

        $response->assertOk()->assertSee('ENCANTADO');
    }

    public function test_update_persists_the_edited_cotas(): void
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

        $response = $this->actingAs(User::factory()->create())->put("/admin/stations/{$station->id}", [
            'name' => 'Prainha do Itaí',
            'river' => null,
            'municipality' => 'Eldorado do Sul',
            'latitude' => -30.006414,
            'longitude' => -51.301534,
            'unit' => 'm',
            'attention_level' => 0.7,
            'alert_level' => 1.0,
            'critical_level' => 1.4,
        ]);

        $response->assertRedirect(route('admin.stations.index'));

        $station->refresh();

        $this->assertSame('Prainha do Itaí', $station->name);
        $this->assertSame(0.7, $station->attention_level);
        $this->assertSame(1.0, $station->alert_level);
        $this->assertSame(1.4, $station->critical_level);
    }

    public function test_update_rejects_invalid_coordinates(): void
    {
        $station = Station::create([
            'source' => 'sigdc',
            'external_id' => 'MON-GM-G01-LEVEL',
            'name' => 'Prainha do Itai',
            'latitude' => -30.006414,
            'longitude' => -51.301534,
            'unit' => 'm',
        ]);

        $response = $this->actingAs(User::factory()->create())->put("/admin/stations/{$station->id}", [
            'name' => 'Prainha do Itai',
            'latitude' => 200,
            'longitude' => -51.301534,
            'unit' => 'm',
        ]);

        $response->assertSessionHasErrors('latitude');
    }
}
