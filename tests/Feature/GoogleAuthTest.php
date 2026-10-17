<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_redirect_url_returns_successful_api_response(): void
    {
        $redirectResponse = Mockery::mock(RedirectResponse::class);
        $redirectResponse->shouldReceive('getTargetUrl')
            ->once()
            ->andReturn('https://accounts.google.com/o/oauth2/v2/auth?client_id=test');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn($redirectResponse);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/redirect');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Google redirect URL generated successfully',
                'data' => [
                    'url' => 'https://accounts.google.com/o/oauth2/v2/auth?client_id=test',
                ],
            ]);
    }

    public function test_login_with_token_validates_required_access_token(): void
    {
        $response = $this->postJson('/api/auth/google/token', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation error',
            ])
            ->assertJsonValidationErrors(['access_token']);
    }

    public function test_login_with_token_creates_new_user_and_returns_jwt_payload(): void
    {
        $providerUser = Mockery::mock(ProviderUser::class);
        $providerUser->shouldReceive('getId')->andReturn('google-id-12345');
        $providerUser->shouldReceive('getName')->andReturn('Alice Wonderland');
        $providerUser->shouldReceive('getEmail')->andReturn('alice@example.com');
        $providerUser->shouldReceive('getAvatar')->andReturn('https://google.com/avatar.png');
        $providerUser->shouldReceive('getNickname')->andReturn('alice');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('userFromToken')->with('valid-google-token')->once()->andReturn($providerUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->postJson('/api/auth/google/token', [
            'access_token' => 'valid-google-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authenticated successfully with Google',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'avatar',
                        'created_at',
                    ],
                    'token' => [
                        'access_token',
                        'token_type',
                        'expires_in',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'google_id' => 'google-id-12345',
            'full_name' => 'Alice Wonderland',
            'system_role' => 'TeamMember',
        ]);
    }

    public function test_login_with_token_links_existing_user_by_email(): void
    {
        $existingUser = User::create([
            'full_name' => 'Bob Existing',
            'email' => 'bob@example.com',
            'password' => bcrypt('secret123'),
            'system_role' => 'Admin',
        ]);

        $providerUser = Mockery::mock(ProviderUser::class);
        $providerUser->shouldReceive('getId')->andReturn('google-id-99999');
        $providerUser->shouldReceive('getName')->andReturn('Bob Existing');
        $providerUser->shouldReceive('getEmail')->andReturn('bob@example.com');
        $providerUser->shouldReceive('getAvatar')->andReturn('https://google.com/bob.png');

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('userFromToken')->with('bob-token')->once()->andReturn($providerUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->postJson('/api/auth/google/token', [
            'access_token' => 'bob-token',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $existingUser->id,
                        'email' => 'bob@example.com',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'google_id' => 'google-id-99999',
            'image' => 'https://google.com/bob.png',
        ]);
    }

    public function test_handle_web_callback_fails_when_user_denies_authorization(): void
    {
        $response = $this->getJson('/api/auth/google/callback?error=access_denied');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Google authorization was denied or cancelled.',
            ]);
    }

    public function test_handle_web_callback_authenticates_and_returns_payload_when_no_frontend_url(): void
    {
        config(['services.google.frontend_url' => null]);

        $providerUser = Mockery::mock(ProviderUser::class);
        $providerUser->shouldReceive('getId')->andReturn('google-callback-id');
        $providerUser->shouldReceive('getName')->andReturn('Carol Callback');
        $providerUser->shouldReceive('getEmail')->andReturn('carol@example.com');
        $providerUser->shouldReceive('getAvatar')->andReturn(null);
        $providerUser->shouldReceive('getNickname')->andReturn(null);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($providerUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->getJson('/api/auth/google/callback?code=mock_code');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authenticated successfully with Google',
                'data' => [
                    'user' => [
                        'name' => 'Carol Callback',
                        'email' => 'carol@example.com',
                    ],
                ],
            ]);
    }

    public function test_handle_web_callback_redirects_to_frontend_when_configured(): void
    {
        config(['services.google.frontend_url' => 'https://app.example.com/oauth/callback']);

        $providerUser = Mockery::mock(ProviderUser::class);
        $providerUser->shouldReceive('getId')->andReturn('google-callback-id-2');
        $providerUser->shouldReceive('getName')->andReturn('David Redirect');
        $providerUser->shouldReceive('getEmail')->andReturn('david@example.com');
        $providerUser->shouldReceive('getAvatar')->andReturn(null);
        $providerUser->shouldReceive('getNickname')->andReturn(null);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($providerUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/api/auth/google/callback?code=mock_code');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://app.example.com/oauth/callback?token=', $response->headers->get('Location'));
    }
}
