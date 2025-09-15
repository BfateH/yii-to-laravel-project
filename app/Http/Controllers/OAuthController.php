<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Exception;

class OAuthController extends Controller
{
    public function redirectToProvider(string $provider): RedirectResponse
    {
        Log::info('OAuth redirect initiated', [
            'provider' => $provider,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString()
        ]);

        // Для VK явно запрашиваем доступ к email
        if ($provider === 'vkontakte') {
            return Socialite::driver($provider)
                ->scopes(['email'])
                ->redirect();
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Обработка callback от провайдера OAuth
     */
    public function handleProviderCallback(string $provider): RedirectResponse
    {
        Log::info('OAuth callback received', [
            'provider' => $provider,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'query_params' => request()->query()
        ]);

        try {
            // Специальная обработка для VKontakte
            if ($provider === 'vkontakte') {
                $socialUser = $this->handleVkontakteCallback();
            } else {
                $socialUser = $this->getSocialiteUserWithRetry($provider);
            }

            Log::debug('Socialite user retrieved', [
                'provider' => $provider,
                'user_id' => $socialUser->getId(),
                'email' => $socialUser->getEmail(),
                'name' => $socialUser->getName()
            ]);

            // Проверяем наличие email
            if (empty($socialUser->getEmail())) {
                $errorMsg = 'Не удалось получить email от провайдера. Разрешите доступ к email в настройках вашего аккаунта.';
                Log::warning('OAuth email missing', [
                    'provider' => $provider,
                    'user_id' => $socialUser->getId(),
                    'user_data' => $socialUser->getRaw()
                ]);

                throw new Exception($errorMsg);
            }

            // Ищем или создаем пользователя
            $user = $this->findOrCreateUser($socialUser, $provider);

            // Аутентифицируем пользователя
            Auth::login($user, true);

            Log::info('OAuth authentication successful', [
                'provider' => $provider,
                'user_id' => $user->id,
                'email' => $user->email,
                'new_user' => $user->wasRecentlyCreated
            ]);

            return redirect()->intended(route('moonshine.index'));

        } catch (Exception $e) {
            Log::error('OAuth authentication failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('moonshine.login')
                ->withErrors(['oauth' => 'Ошибка аутентификации через ' . ucfirst($provider)
                    . ': ' . $e->getMessage()]);
        }
    }

    /**
     * Получение пользователя Socialite с retry механизмом
     */
    protected function getSocialiteUserWithRetry(string $provider, int $maxAttempts = 5)
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $maxAttempts) {
            try {
                Log::debug('Attempting to get Socialite user', [
                    'provider' => $provider,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts
                ]);

                if ($attempt > 1) {
                    // Добавляем небольшую задержку между попытками
                    usleep(300000 * $attempt); // 0.3s * attempt
                }

                return Socialite::driver($provider)->user();

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('Socialite attempt failed', [
                    'provider' => $provider,
                    'attempt' => $attempt,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_class' => get_class($e)
                ]);

                $attempt++;

                // Если это последняя попытка, выходим
                if ($attempt > $maxAttempts) {
                    break;
                }
            }
        }

        throw new Exception('Не удалось выполнить аутентификацию. Попробуйте еще раз.');
    }

    protected function handleVkontakteCallback(): \Laravel\Socialite\Contracts\User
    {
        $socialUser = Socialite::driver('vkontakte')->user();

        Log::debug('VKontakte user data', [
            'raw_data' => $socialUser->getRaw(),
            'access_token_response' => $socialUser->accessTokenResponseBody
        ]);

        // VK может возвращать email через отдельный метод или в accessTokenResponseBody
        $email = $socialUser->getEmail() ?? $socialUser->accessTokenResponseBody['email'] ?? null;

        if (empty($email)) {
            Log::warning('VKontakte email missing', [
                'user_id' => $socialUser->getId(),
                'response_body' => $socialUser->accessTokenResponseBody
            ]);
            throw new Exception('Email not provided by VKontakte');
        }

        // Подменяем email для дальнейшей обработки
        $socialUser->email = $email;

        return $socialUser;
    }

    /**
     * Поиск или создание пользователя
     */
    protected function findOrCreateUser(SocialiteUser $socialUser, string $provider): User
    {
        $email = $socialUser->getEmail();
        $providerId = $socialUser->getId();
        $providerField = $provider . '_id';

        Log::debug('Finding or creating user', [
            'provider' => $provider,
            'email' => $email,
            'provider_id' => $providerId
        ]);

        // Сначала ищем по provider_id
        $user = User::where($providerField, $providerId)->first();

        if ($user) {
            Log::info('User found by provider ID', [
                'provider' => $provider,
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return $user;
        }

        // Если не нашли по provider_id, ищем по email
        $user = User::where('email', $email)->first();

        if ($user) {
            // Обновляем provider_id если его не было
            if (empty($user->$providerField)) {
                $user->update([$providerField => $providerId]);
                Log::info('Updated user with provider ID', [
                    'provider' => $provider,
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            } else {
                Log::warning('Provider ID mismatch', [
                    'provider' => $provider,
                    'user_id' => $user->id,
                    'existing_provider_id' => $user->$providerField,
                    'new_provider_id' => $providerId
                ]);
            }

            return $user;
        }

        // Создаем нового пользователя
        $user = User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? explode('@', $socialUser->getEmail())[0],
            'email' => $email,
            'password' => Hash::make(Str::random(24)),
            $providerField => $providerId,
        ]);

        Log::info('New user created via OAuth', [
            'provider' => $provider,
            'user_id' => $user->id,
            'email' => $email,
            'name' => $user->name
        ]);

        return $user;
    }
}
