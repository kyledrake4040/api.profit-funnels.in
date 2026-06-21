<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Passport needs signing keys and a personal access client to issue tokens.
        Artisan::call('passport:keys', ['--force' => true]);
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test Personal', '--no-interaction' => true]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('correct-horse'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_login_issues_an_access_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('correct-horse'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'correct-horse',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'token_type' => 'Bearer',
                'user' => ['id' => $user->id, 'email' => 'owner@example.com'],
            ])
            ->assertJsonStructure(['access_token']);

        $this->assertNotEmpty($response->json('access_token'));
    }
}
