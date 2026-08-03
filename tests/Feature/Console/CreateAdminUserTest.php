<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_with_a_hashed_password(): void
    {
        $this->artisan('admin:create-user', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'senha12345',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(password_verify('senha12345', $user->password));
    }

    public function test_it_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('admin:create-user', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'senha12345',
        ])->assertFailed();
    }
}
