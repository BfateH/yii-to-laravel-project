# 🚀 Запуск проекта

Для успешного запуска проекта выполните следующие шаги:

## 📋 Контрольный список установки

| Статус | Задача                         | Команда / Действие                |
|:------:|--------------------------------|-----------------------------------|
|   ✅    | Установка PHP-зависимостей     | `composer install`                |
|   ✅    | Настройка окружения            | Настроить `.env`                  |
|   ✅    | Генерация ключа приложения     | `php artisan key:generate`        |
|   ✅    | Генерация JWT секрета          | `php artisan jwt:secret`          |
|   ✅    | Установка Node.js зависимостей | `npm install`                     |
|   ✅    | Сборка фронтенда               | `npm run build`                   |
|   ✅    | Миграция и наполнение БД       | `php artisan migrate --seed`      |
|   ✅    | Генерация документации API     | `php artisan l5-swagger:generate` |
|   ✅    | Запуск development сервера     | `php artisan serve`               |
|   ✅    | Запуск очередей                | `php artisan queue:work`          |
|   ✅    | Запуск Websocket сервера       | `php artisan reverb:start`        |

---

## 🔗 Дополнения

- **Swagger документация**: `/api/documentation`
- **Запуск тестов**: `php artisan test`
- **Получение курсов валют (нужен cron, автоматически в 01:00 обновляет)** 

`php artisan currency:update-rates`


    currency:update-rates
    {date? : Дата для обновления курсов (Y-m-d). По умолчанию - вчерашний день.}
    {--force : Принудительно выполнить обновление, игнорируя возможные ограничения.}

- **Опрос посылок для обновления их статусов и событий отслеживания (нужен cron, автоматически каждые 5 минут)** 

`php artisan tracking:poll-shipments`


    tracking:poll-shipments
    {--limit=100 : Количество посылок для обработки за один запуск}
    {--status=* : Статусы посылок для опроса (например, --status=6 --status=7). По умолчанию: SENT, RECEIVED}
    {--force-refresh : Принудительно обновить данные, игнорируя кэш}

Команда отправляет в очередь обработку посылок. 

Для работы нужен `php artisan queue:work`

---

## 🔔 Уведомления

Для работы уведомлений нужен `php artisan queue:work`

Для уведомлений `telegram` указать в `.env` переменную `TELEGRAM_BOT_TOKEN=`

Для работы `webpush` уведомлений нужно разрешить сайту присылать уведомления, 
добавить в `.env` переменные `WEBPUSH_PUBLIC_KEY=` и `WEBPUSH_PRIVATE_KEY=`.

Сгенерировать эти ключи можно командой `php artisan webpush:generate-keys`. 
После использования команды, она выдаст сгенерированные ключи.

---

## 🔄 Модуль поддержки и Websockets

Для работы `Websockets` нужно включить `php artisan reverb:start` и настроить в `.env` следующие переменные:

    BROADCAST_CONNECTION=reverb

    REVERB_APP_ID=
    REVERB_APP_KEY=
    REVERB_APP_SECRET=
    REVERB_HOST=
    REVERB_PORT=
    REVERB_SCHEME=
    
    VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
    VITE_REVERB_HOST="${REVERB_HOST}"
    VITE_REVERB_PORT="${REVERB_PORT}"
    VITE_REVERB_SCHEME="${REVERB_SCHEME}"

---

## 📦 Модуль тркенига для посылок (Почта России)

Для работы нужно настроить в `.env` переменные

    RUSPOST_CLIENT_ID=
    RUSPOST_CLIENT_SECRET=

---

## 💳 Модуль эквайринга

Для работы нужно настроить в `.env` переменную `ACQUIRING_ENCRYPTION_KEY=`

---

## 🔐 SSO-аутентификация

### Предварительная настройка
**0.** Настройте доступные провайдеры в `/config/sso.php`

### Шаг 1: Получение токена
**POST** `/api/auth/token-login`

**Параметры:**
- `provider` - провайдер аутентификации
- `token` - JWT токен (обязательно содержит поле `email`)

**Тестировал здесь:** https://token.dev/

#### Пример JWT токена
    eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6InRlc3QtcnNhIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWUsImlhdCI6MTc1NzA2MTc4NiwiZXhwIjoxNzk5MDY1Mzg2LCJlbWFpbCI6InRlc3RAbWFpbC5ydSIsImlzcyI6Imh0dHBzOi8vdG9rZW4uZGV2IiwiYXVkIjoiYXBpOi8vZGVmYXVsdCIsImp0aSI6IjEyMzEyMyJ9.hDeCWJVyS3gwXK3AYNSSkadEffDlSqu_fVyH-RQo4gFZI5IHew7NAeeL77Gtmsb1AhqJVAKwcyU3EG2RmxjKN6Sy-qja9T4fiZC4PMUghHsyp2hyJZqoYwVamfnOVXHEGpNt5KYO_ONcP-KebmK77ULmoCNUr0NCHPkLuNPOxFPlKNE-bmoC-eusEQWQU4b-33DkahzhfkOppdS25WjL-Zefe6Z3NBHK-m8LIz47BZ8Uzn8se3tYK3IAbCe1QbocgZjo699sdjC8lsVjVb5qaItMHLe0IPQin8PACC9TGOXjJzZd94cmJqFBnRTowCxBcUXFUBD73KzTbyJyS8pV6g


**Header:**

    {
        "typ": "JWT",
        "alg": "RS256",
        "kid": "test-rsa"
    }

**Payload:**

    {
        "sub": "1234567890",
        "name": "John Doe",
        "admin": true,
        "iat": 1757061786,
        "exp": 1799065386,
        "email": "test@mail.ru",
        "iss": "https://token.dev",
        "aud": "api://default",
        "jti": "123123"
    }

### Шаг 2: Обработка ответа сервера
- Получаем токен из поля `access_token`
- Сохраняем токен (например, в Postman)
- Используем авторизацию: `Authorization: Bearer ваш_токен`

### Шаг 3: Использование API
- **Тестирование**: GET `/api/me`
- **Выход из системы**: GET `/api/logout`

---

## 🔑 Наши собственные API токены

- **Получение токена**: POST `/api/auth/tokens`
- **Выход из системы**: GET `/api/auth/tokens/logout`
- Для входа используем `password` и `email` указанный при регистрации
- От сервера получаем `token`, который можно передавать как:
    - query параметр `api_key`
    - в заголовке `X-API-Key`

---

## 🔗 Redirect URLs для OAuth

- **Google**: `/auth/google/callback`
- **Yandex**: `/auth/yandex/callback`
- **Vkontakte**: `/auth/vkontakte/callback`
- **MailRu**: `/auth/mailru/callback`

**Все секреты в файле `.env`**
