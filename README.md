## Для запуска проекта

- composer install
- Настроить .env
- php artisan key:generate
- php artisan jwt:secret
- npm install
- npm run build
- php artisan migrate --seed
- php artisan l5-swagger:generate
- php artisan serve

**Swagger тут /api/documentation**
**Можно ещё запустить php artisan test для проверки тестов**

## Описание SSO‑потока

**0. Настраиваем доступные провайдеры в /config/sso.php**

**1. Стучимся сюда POST /api/auth/token-login**

- поля provider и token, в payload у токена обязательно email
- Тестировал здесь https://token.dev/

Пример токена
eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6InRlc3QtcnNhIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWUsImlhdCI6MTc1NzA2MTc4NiwiZXhwIjoxNzk5MDY1Mzg2LCJlbWFpbCI6InRlc3RAbWFpbC5ydSIsImlzcyI6Imh0dHBzOi8vdG9rZW4uZGV2IiwiYXVkIjoiYXBpOi8vZGVmYXVsdCIsImp0aSI6IjEyMzEyMyJ9.hDeCWJVyS3gwXK3AYNSSkadEffDlSqu_fVyH-RQo4gFZI5IHew7NAeeL77Gtmsb1AhqJVAKwcyU3EG2RmxjKN6Sy-qja9T4fiZC4PMUghHsyp2hyJZqoYwVamfnOVXHEGpNt5KYO_ONcP-KebmK77ULmoCNUr0NCHPkLuNPOxFPlKNE-bmoC-eusEQWQU4b-33DkahzhfkOppdS25WjL-Zefe6Z3NBHK-m8LIz47BZ8Uzn8se3tYK3IAbCe1QbocgZjo699sdjC8lsVjVb5qaItMHLe0IPQin8PACC9TGOXjJzZd94cmJqFBnRTowCxBcUXFUBD73KzTbyJyS8pV6g

Пример header токена

{
  "typ": "JWT",
  "alg": "RS256",
  "kid": "test-rsa"
}

Пример payload токена 

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

**2. Нам отвечает сервер с данными**

- берем токен из поля access_token
- сохраняем его, например в postman. Authorisation: Bearer наш_токен

**3. Всё, можем использовать api**
- Например, для теста идем сюда GET /api/me
- Или сюда для выхода GET /api/logout

## Наши собственные API токены 
- Выдаются здесь POST /api/auth/tokens
- Выходим здесь GET /api/auth/tokens/logout
- Для входа используем password и email указанный при регистрации
- от сервера получаем token, который можно передавать как query параметр api_key либо в заголовке X-API-Key

## При настройке OAuth в личных кабинетах нужно указать RedirectURL
- Для Google /auth/google/callback
- Для Yandex /auth/yandex/callback
- Для Vkontakte /auth/vkontakte/callback
- Для MailRu /auth/mailru/callback

**Все секреты в файле .env**
