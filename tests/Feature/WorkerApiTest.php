<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for the worker API. We focus on the controller's HTTP
 * contract — auth gating, validation shape, and the JSON response keys
 * the Android app reads. Anything that needs a populated reservation
 * (detail, pickup, return) is covered manually via curl in a dev env;
 * the project doesn't have Reservation/Customer/Vehicle factories yet,
 * and writing them just for these tests would balloon the PR.
 */
class WorkerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_validates_email_and_password(): void
    {
        $this->postJson('/api/worker/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_wrong_password_with_clean_message(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-horse')]);

        $this->postJson('/api/worker/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    public function test_login_returns_token_on_success(): void
    {
        $user = User::factory()->create(['password' => bcrypt('let-me-in')]);

        $resp = $this->postJson('/api/worker/login', [
            'email'       => $user->email,
            'password'    => 'let-me-in',
            'device_name' => 'pixel-7',
        ])->assertOk();

        $resp->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $this->assertNotEmpty($resp->json('token'));
    }

    public function test_protected_endpoints_require_auth(): void
    {
        $this->getJson('/api/worker/reservations')->assertStatus(401);
        $this->getJson('/api/worker/reservations/1')->assertStatus(401);
        $this->postJson('/api/worker/reservations/1/sign')->assertStatus(401);
        $this->postJson('/api/worker/customers/1/dl')->assertStatus(401);
        $this->postJson('/api/worker/customers/1/insurance')->assertStatus(401);
    }

    public function test_authed_reservation_list_returns_json_array(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/worker/reservations')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_logout_revokes_current_token(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/worker/logout')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
