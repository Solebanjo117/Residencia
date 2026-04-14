<?php

namespace App\Support\Auth;

final class SocialProviderRegistry
{
    /**
     * @return array<string, array{driver: string, label: string}>
     */
    public static function supported(): array
    {
        return [
            'google' => [
                'driver' => 'google',
                'label' => 'Google',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::supported());
    }

    public static function has(string $provider): bool
    {
        return array_key_exists($provider, self::supported());
    }

    /**
     * @return array{driver: string, label: string}|null
     */
    public static function get(string $provider): ?array
    {
        return self::supported()[$provider] ?? null;
    }

    public static function isEnabled(string $provider): bool
    {
        if (!self::has($provider)) {
            return false;
        }

        $enabledProviders = config('services.social_auth.enabled_providers', []);
        if (!in_array($provider, $enabledProviders, true)) {
            return false;
        }

        $config = config('services.' . $provider, []);

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null);
    }

    /**
     * @return array<int, array{name: string, label: string, login_url: string}>
     */
    public static function enabledForLogin(): array
    {
        $providers = [];

        foreach (self::supported() as $name => $meta) {
            if (!self::isEnabled($name)) {
                continue;
            }

            $providers[] = [
                'name' => $name,
                'label' => $meta['label'],
                'login_url' => route('auth.social.redirect', ['provider' => $name]),
            ];
        }

        return $providers;
    }
}
