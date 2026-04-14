<?php

use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    Config::set('services.social_auth.enabled_providers', ['google']);
    Config::set('services.google', [
        'client_id' => 'google-client-id',
        'client_secret' => 'google-client-secret',
        'redirect' => 'https://residencia.test/auth/google/callback',
    ]);
});

it('shows the configured social provider on the login screen', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->has('socialProviders', 1)
            ->where('socialProviders.0.name', 'google')
            ->where('socialProviders.0.label', 'Google')
        );
});

it('redirects the user to google using socialite', function () {
    $provider = \Mockery::mock();
    $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('auth.social.redirect', ['provider' => 'google']))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

it('logs in an existing user by email and links the google account', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'social-existing@example.com',
        'is_active' => true,
    ]);

    $provider = \Mockery::mock();
    $provider->shouldReceive('user')->once()->andReturn(
        socialiteGoogleUser([
            'id' => 'google-user-123',
            'name' => 'Social Existing',
            'email' => 'social-existing@example.com',
            'avatar' => 'https://example.com/avatar.png',
            'email_verified' => true,
        ])
    );

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $response = $this->get(route('auth.social.callback', ['provider' => 'google']));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user->fresh());

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-user-123',
        'provider_email' => 'social-existing@example.com',
    ]);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('creates a new docente user when the google email does not exist locally', function () {
    $provider = \Mockery::mock();
    $provider->shouldReceive('user')->once()->andReturn(
        socialiteGoogleUser([
            'id' => 'google-new-456',
            'name' => 'Nuevo Social',
            'email' => 'nuevo-social@example.com',
            'avatar' => 'https://example.com/new-avatar.png',
            'email_verified' => true,
        ])
    );

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $response = $this->get(route('auth.social.callback', ['provider' => 'google']));

    $response->assertRedirect(route('dashboard'));

    $createdUser = User::query()->where('email', 'nuevo-social@example.com')->first();

    expect($createdUser)->not->toBeNull();
    expect($createdUser->role->name)->toBe(Role::DOCENTE);
    expect($createdUser->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($createdUser);

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $createdUser->id,
        'provider' => 'google',
        'provider_user_id' => 'google-new-456',
    ]);
});

it('handles social login cancellation cleanly', function () {
    $this->get(route('auth.social.callback', [
        'provider' => 'google',
        'error' => 'access_denied',
    ]))
        ->assertRedirect(route('login'))
        ->assertSessionHas('social_auth_error', 'Se cancelo el acceso con Google.');

    $this->assertGuest();
});

it('rejects a social callback without a trusted email', function () {
    $provider = \Mockery::mock();
    $provider->shouldReceive('user')->once()->andReturn(
        socialiteGoogleUser([
            'id' => 'google-missing-email',
            'name' => 'Sin Correo',
            'email' => null,
            'avatar' => null,
            'email_verified' => true,
        ])
    );

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('social_auth_error', 'La cuenta de Google no devolvio un correo electronico valido.');

    $this->assertGuest();
    expect(SocialAccount::count())->toBe(0);
});

function socialiteGoogleUser(array $attributes): SocialiteUser
{
    return (new SocialiteUser())
        ->setRaw([
            'sub' => $attributes['id'],
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'email_verified' => $attributes['email_verified'],
            'picture' => $attributes['avatar'],
        ])
        ->map([
            'id' => $attributes['id'],
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'avatar' => $attributes['avatar'],
        ]);
}
