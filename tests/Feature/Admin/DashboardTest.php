<?php

namespace Tests\Feature\Admin;

use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_it_reports_fresh_and_stale_stations_per_source(): void
    {
        $fresh = Station::create([
            'source' => 'sigdc',
            'external_id' => 'fresh-1',
            'name' => 'Fresca',
            'latitude' => -30,
            'longitude' => -51,
            'unit' => 'm',
        ]);
        $fresh->readings()->create(['value' => 1, 'measured_at' => now(), 'source' => 'sigdc']);

        $stale = Station::create([
            'source' => 'sigdc',
            'external_id' => 'stale-1',
            'name' => 'Velha',
            'latitude' => -30,
            'longitude' => -51,
            'unit' => 'm',
        ]);
        $stale->readings()->create(['value' => 1, 'measured_at' => now()->subHours(10), 'source' => 'sigdc']);

        $response = $this->actingAs(User::factory()->create())->get('/admin');

        $response->assertOk();

        $ingestion = $response->viewData('ingestion');

        $this->assertSame(2, $ingestion['sigdc']['total']);
        $this->assertSame(1, $ingestion['sigdc']['fresh']);
        $this->assertSame(0, $ingestion['sace']['total']);
    }
}
