<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/dashboard', '/websites', '/websites/create', '/settings', '/profile', '/businesses'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_login_and_register_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p->component('Auth/Login'));
        $this->get('/register')->assertOk()->assertInertia(fn ($p) => $p->component('Auth/Register'));
    }

    public function test_user_can_register(): void
    {
        $this->post('/register', [
            'name' => 'Asha Patel',
            'email' => 'Asha@Example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'asha@example.com']);
    }

    public function test_registration_validates_password_strength_and_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', ['name' => 'A', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->post('/register', ['name' => 'A', 'email' => 'new@example.com', 'password' => 'lettersonly', 'password_confirmation' => 'lettersonly'])
            ->assertSessionHasErrors(['password']);
        $this->post('/register', ['name' => 'A', 'email' => 'new@example.com', 'password' => 'secret123', 'password_confirmation' => 'different1'])
            ->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_and_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_profile_and_password_can_be_updated(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->actingAs($user)->put('/profile', ['name' => 'New Name', 'email' => 'new@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('New Name', $user->fresh()->name);

        $this->actingAs($user)->put('/profile/password', ['current_password' => 'wrong', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'secret123', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertSessionHasNoErrors();
    }

    public function test_settings_save_default_tone_and_show_integration_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/settings')->assertOk()->assertInertia(fn ($p) => $p
            ->component('Settings')
            ->where('integrations.ai.configured', false)
            ->where('integrations.search.configured', false));

        $this->actingAs($user)->put('/settings', ['default_tone' => 'friendly'])->assertSessionHasNoErrors();
        $this->assertSame('friendly', $user->fresh()->preference('default_tone'));
        $this->actingAs($user)->put('/settings', ['default_tone' => 'evil'])->assertSessionHasErrors('default_tone');
    }
}
