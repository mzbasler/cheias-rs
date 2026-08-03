<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect(route('login'));
    }

    public function test_update_persists_the_pix_key(): void
    {
        $response = $this->actingAs(User::factory()->create())->put('/admin/settings', [
            'pix_key' => 'chave@exemplo.com',
            'pix_receiver_name' => 'Cheias RS',
            'pix_receiver_city' => 'PORTO ALEGRE',
        ]);

        $response->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('chave@exemplo.com', Setting::current()->pix_key);
    }

    public function test_the_map_reflects_the_saved_pix_key(): void
    {
        Setting::current()->update(['pix_key' => 'chave@exemplo.com']);

        $pix = $this->get('/')->viewData('pix');

        $this->assertSame('chave@exemplo.com', $pix['key']);
    }
}
