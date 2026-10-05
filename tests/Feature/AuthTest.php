<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/data')->assertRedirect('/login');
    }

    public function test_user_can_login_with_correct_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah']);
        }

        $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah'])
            ->assertTooManyRequests();
    }

    public function test_user_can_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_viewer_cannot_access_data_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/data')
            ->assertForbidden();
    }

    public function test_viewer_does_not_see_data_menu(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('data'));
    }

    public function test_admin_can_access_data_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/data')
            ->assertOk();
    }
}
