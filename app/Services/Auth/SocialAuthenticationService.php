<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use RuntimeException;

class SocialAuthenticationService
{
    public function authenticate(string $provider, ProviderUser $providerUser): User
    {
        $providerUserId = trim((string) $providerUser->getId());
        if ($providerUserId === '') {
            throw new RuntimeException('No fue posible identificar tu cuenta en el proveedor.');
        }

        $email = $this->normalizedEmail($providerUser->getEmail());
        if ($email === null) {
            throw new RuntimeException('La cuenta de ' . ucfirst($provider) . ' no devolvio un correo electronico valido.');
        }

        if (!$this->isTrustedEmail($providerUser)) {
            throw new RuntimeException('El correo devuelto por ' . ucfirst($provider) . ' no pudo verificarse de forma segura.');
        }

        $socialAccount = SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;

            $this->assertActiveUser($user);
            $this->syncSocialAccount($socialAccount, $providerUser, $email);
            $this->markEmailAsVerified($user);

            return $user;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user) {
            $this->assertActiveUser($user);

            $existingLink = $user->socialAccounts()
                ->where('provider', $provider)
                ->first();

            if ($existingLink && $existingLink->provider_user_id !== $providerUserId) {
                throw new RuntimeException('Esta cuenta local ya esta vinculada a otra cuenta de ' . ucfirst($provider) . '.');
            }

            $this->linkSocialAccount($user, $provider, $providerUser, $email, $existingLink);
            $this->markEmailAsVerified($user);

            return $user;
        }

        $user = User::create([
            'name' => $this->resolvedUserName($providerUser, $email),
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'role_id' => Role::where('name', Role::DOCENTE)->value('id') ?? 1,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->linkSocialAccount($user, $provider, $providerUser, $email);

        return $user;
    }

    private function linkSocialAccount(
        User $user,
        string $provider,
        ProviderUser $providerUser,
        string $email,
        ?SocialAccount $socialAccount = null
    ): SocialAccount {
        $socialAccount ??= new SocialAccount([
            'provider' => $provider,
            'provider_user_id' => (string) $providerUser->getId(),
            'linked_at' => now(),
        ]);

        $socialAccount->user()->associate($user);
        $this->syncSocialAccount($socialAccount, $providerUser, $email);

        return $socialAccount;
    }

    private function syncSocialAccount(SocialAccount $socialAccount, ProviderUser $providerUser, string $email): void
    {
        if (!$socialAccount->exists && !$socialAccount->linked_at) {
            $socialAccount->linked_at = now();
        }

        $socialAccount->forceFill([
            'provider_user_id' => (string) $providerUser->getId(),
            'provider_email' => $email,
            'avatar' => $providerUser->getAvatar(),
            'provider_data' => $this->providerData($providerUser),
            'last_used_at' => now(),
        ])->save();
    }

    private function resolvedUserName(ProviderUser $providerUser, string $email): string
    {
        $name = trim((string) $providerUser->getName());

        if ($name !== '') {
            return $name;
        }

        return Str::title((string) Str::of($email)->before('@')->replace(['.', '_', '-'], ' '));
    }

    private function normalizedEmail(?string $email): ?string
    {
        $normalized = Str::lower(trim((string) $email));

        return $normalized !== '' ? $normalized : null;
    }

    private function assertActiveUser(User $user): void
    {
        if (!$user->is_active) {
            throw new RuntimeException('Tu cuenta esta desactivada. Contacta al administrador.');
        }
    }

    private function markEmailAsVerified(User $user): void
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }

    /**
     * Accept missing explicit verification metadata, but reject explicit false values.
     */
    private function isTrustedEmail(ProviderUser $providerUser): bool
    {
        $raw = $this->providerData($providerUser);
        $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? null;

        return $verified !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private function providerData(ProviderUser $providerUser): array
    {
        if (method_exists($providerUser, 'getRaw')) {
            $raw = $providerUser->getRaw();

            return is_array($raw) ? $raw : [];
        }

        if (property_exists($providerUser, 'user') && is_array($providerUser->user)) {
            return $providerUser->user;
        }

        return [];
    }
}
