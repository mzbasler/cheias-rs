<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_visiting_the_dashboard(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('login'));
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);

        $this->from(route('login'))
            ->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_correct_credentials_log_the_admin_in(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
