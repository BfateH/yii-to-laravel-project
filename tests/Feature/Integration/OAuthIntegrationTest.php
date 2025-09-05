<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class OAuthIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_oauth_full_flow()
    {
        // 1. Проверяем редирект на Google OAuth
        $response = $this->get('/auth/google/redirect');
        $response->assertRedirect();

        // Извлекаем URL редиректа и проверяем, что он ведет к Google
        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('accounts.google.com', $redirectUrl);
        $this->assertStringContainsString('client_id=', $redirectUrl);
        $this->assertStringContainsString('redirect_uri=', $redirectUrl);
        $this->assertStringContainsString('response_type=code', $redirectUrl);

        // 2. Мокируем пользователя Socialite
        $socialiteUser = new SocialiteUser;
        $socialiteUser->map([
            'id' => 'google-123',
            'email' => 'google@example.com',
            'name' => 'Google User',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        // 3. Имитируем callback от Google
        $callbackResponse = $this->get('/auth/google/callback?code=test-code');

        // 4. Проверяем создание пользователя
        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'google_id' => 'google-123',
        ]);

        // 5. Проверяем редирект и аутентификацию
        $callbackResponse->assertRedirect(route('moonshine.index'));
        $this->assertAuthenticated();

        // 6. Проверяем установку сессии
        $this->assertNotNull(session()->getId());
    }

    public function test_yandex_oauth_full_flow()
    {
        // Проверяем редирект на Yandex OAuth
        $response = $this->get('/auth/yandex/redirect');
        $response->assertRedirect();

        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('oauth.yandex.ru', $redirectUrl);

        // Мокируем пользователя Socialite для Yandex
        $socialiteUser = new SocialiteUser;
        $socialiteUser->map([
            'id' => 'yandex-123',
            'email' => 'yandex@example.com',
            'name' => 'Yandex User',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        // Имитируем callback от Yandex
        $callbackResponse = $this->get('/auth/yandex/callback?code=test-code');

        // Проверяем создание пользователя
        $this->assertDatabaseHas('users', [
            'email' => 'yandex@example.com',
            'yandex_id' => 'yandex-123',
        ]);

        $callbackResponse->assertRedirect(route('moonshine.index'));
        $this->assertAuthenticated();
    }

    public function test_vkontakte_oauth_full_flow()
    {
        // Проверяем редирект на VK OAuth
        $response = $this->get('/auth/vkontakte/redirect');
        $response->assertRedirect();

        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('oauth.vk.com', $redirectUrl);

        // Мокируем пользователя Socialite для VK
        $socialiteUser = new SocialiteUser;
        $socialiteUser->map([
            'id' => 'vk-123',
            'email' => 'vk@example.com',
            'name' => 'VK User',
            'avatar' => 'https://example.com/vk-avatar.jpg',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        // Имитируем callback от VK
        $callbackResponse = $this->get('/auth/vkontakte/callback?code=test-code');

        // Проверяем создание пользователя
        $this->assertDatabaseHas('users', [
            'email' => 'vk@example.com',
            'vkontakte_id' => 'vk-123',
        ]);

        $callbackResponse->assertRedirect(route('moonshine.index'));
        $this->assertAuthenticated();
    }

    public function test_mailru_oauth_full_flow()
    {
        // Проверяем редирект на Mail.ru OAuth
        $response = $this->get('/auth/mailru/redirect');
        $response->assertRedirect();

        $redirectUrl = $response->headers->get('Location');
        $this->assertStringContainsString('oauth.mail.ru', $redirectUrl);

        // Мокируем пользователя Socialite для Mail.ru
        $socialiteUser = new SocialiteUser;
        $socialiteUser->map([
            'id' => 'mailru-123',
            'email' => 'mailru@example.com',
            'name' => 'Mail.ru User',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        // Имитируем callback от Mail.ru
        $callbackResponse = $this->get('/auth/mailru/callback?code=test-code');

        // Проверяем создание пользователя
        $this->assertDatabaseHas('users', [
            'email' => 'mailru@example.com',
            'mailru_id' => 'mailru-123',
        ]);

        $callbackResponse->assertRedirect(route('moonshine.index'));
        $this->assertAuthenticated();
    }

    public function test_oauth_flow_for_existing_user_preserves_name()
    {
        // Создаем пользователя заранее с определенным именем
        $originalName = 'Old Name';
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
            'name' => $originalName,
        ]);

        // Мокируем пользователя Socialite с новым именем
        $socialiteUser = new SocialiteUser;
        $socialiteUser->map([
            'id' => 'google-456',
            'email' => 'existing@example.com', // Тот же email
            'name' => 'Updated Name', // Новое имя, которое НЕ должно быть сохранено
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $callbackResponse = $this->get('/auth/google/callback?code=test-code');

        // Проверяем, что google_id обновился
        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'google_id' => 'google-456',
        ]);

        // Проверяем, что имя осталось неизменным (не обновилось)
        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'name' => $originalName, // Ожидаем исходное имя
        ]);

        // Проверяем, что имя НЕ изменилось на новое
        $this->assertDatabaseMissing('users', [
            'email' => 'existing@example.com',
            'name' => 'Updated Name',
        ]);

        // Дополнительная проверка: убеждаемся, что пользователь не был создан заново
        $this->assertDatabaseCount('users', 1);

        // Проверяем, что ID пользователя остался тем же
        $updatedUser = User::where('email', 'existing@example.com')->first();
        $this->assertEquals($user->id, $updatedUser->id);
        $this->assertEquals($originalName, $updatedUser->name); // Имя должно сохраниться

        $callbackResponse->assertRedirect(route('moonshine.index'));
        $this->assertAuthenticated();
        $this->assertEquals($user->id, auth()->id());
    }

    public function test_oauth_failed_authentication()
    {
        // Мокируем неудачную аутентификацию
        Socialite::shouldReceive('driver->user')->andThrow(new \Exception('Authentication failed'));

        // Имитируем callback с ошибкой
        $callbackResponse = $this->get('/auth/google/callback?code=invalid-code');

        // Используем фактическое сообщение об ошибке из вашего приложения
        $callbackResponse->assertRedirect(route('moonshine.login'));
        $callbackResponse->assertSessionHasErrors(['oauth' => 'Ошибка аутентификации через Google: Не удалось выполнить аутентификацию. Попробуйте еще раз.']);

        // Проверяем, что пользователь не аутентифицирован
        $this->assertGuest();
    }

    public function test_oauth_handles_missing_email()
    {
        $socialiteUser = new SocialiteUser;
        $socialiteUser->map([
            'id' => '123',
            'email' => null, // Email отсутствует
            'name' => 'Test User',
        ]);

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get('/auth/google/callback?code=test-code');

        // Используем фактическое сообщение об ошибке из вашего приложения
        $response->assertRedirect(route('moonshine.login'));
        $response->assertSessionHasErrors(['oauth' => 'Ошибка аутентификации через Google: Не удалось получить email от провайдера. Разрешите доступ к email в настройках вашего аккаунта.']);

        // Проверяем, что пользователь не создан
        $this->assertDatabaseMissing('users', [
            'google_id' => '123'
        ]);
    }
}
