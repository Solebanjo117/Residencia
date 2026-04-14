<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\SocialProviderRegistry;
use App\Services\Auth\SocialAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        if (!SocialProviderRegistry::isEnabled($provider)) {
            return redirect()->route('login')->with('social_auth_error', 'El acceso con ' . ucfirst($provider) . ' no esta disponible en este momento.');
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider, SocialAuthenticationService $service): RedirectResponse
    {
        if (!SocialProviderRegistry::isEnabled($provider)) {
            return redirect()->route('login')->with('social_auth_error', 'El acceso con ' . ucfirst($provider) . ' no esta disponible en este momento.');
        }

        if ($request->filled('error')) {
            return redirect()->route('login')->with('social_auth_error', 'Se cancelo el acceso con ' . ucfirst($provider) . '.');
        }

        try {
            $providerUser = Socialite::driver($provider)->user();
            $user = $service->authenticate($provider, $providerUser);
        } catch (Throwable $exception) {
            Log::warning('social_auth.callback_failed', [
                'provider' => $provider,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('login')->with('social_auth_error', $this->resolveErrorMessage($exception, $provider));
        }

        if ($this->requiresTwoFactorChallenge($user)) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => true,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function requiresTwoFactorChallenge($user): bool
    {
        return Features::canManageTwoFactorAuthentication()
            && !is_null($user->two_factor_secret)
            && !is_null($user->two_factor_confirmed_at);
    }

    private function resolveErrorMessage(Throwable $exception, string $provider): string
    {
        $message = trim($exception->getMessage());

        if ($message !== '') {
            return $message;
        }

        return 'No se pudo completar el acceso con ' . ucfirst($provider) . '. Intenta nuevamente.';
    }
}
