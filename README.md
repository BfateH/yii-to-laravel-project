## Для запуска проекта

- composer install
- Настроить .env
- php artisan key:generate
- php artisan jwt:secret
- npm install
- npm run build
- php artisan migrate --seed
- php artisan serve

## Описание SSO‑потока

**0. Настраиваем доступные провайдеры в /config/sso.php**

**1. Стучимся сюда /api/auth/token-login**

- поля provider и token, в payload у токена обязательно email
- Тестировал здесь https://token.dev/

Пример токена
eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6InRlc3QtcnNhIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWUsImlhdCI6MTc1Njk4MTI3MCwiZXhwIjoxOTk4OTg0ODcxLCJlbWFpbCI6InRlc3RAbWFpbC5ydSIsImp0aSI6InRlc3QxMTFzcyJ9.syMea09cTuwCM3BEkBaQUXI3LTXjrqpVVTUYHpr_P1fV2FZY4MbHbjch6a0d68SbTWudMfDVjKD7zvxDdJqo8ajW6rDoG2wq2E0nt-J0SF5-2X39n2vWp_wA8JS0IqjSb7X4DWBPLGUK77OWSYNVePh5BJrsZPnilEMz5MxSPvnFGfndHnI1gxW75E7e7HkeFr5VLpB-orpdN8iZiHJUXNvZpzvK_DKrpOz2zb1iJJoWjkS-wPAgxoBiKk_WIxbfGL7wqqV8aspgCOI8fv1-o2_Evyut9CzEDvdmkz42NzaVJ1jcQfuOZaKZx4wFJ6PXh_TJQQpxbxo5cuIIPaBOFg

**2. Нам отвечает сервер с данными**

- берем токен из поля access_token
- сохраняем его, например в postman. Authorisation: Bearer наш_токен

**3. Всё, можем использовать api**
- Например, для теста идем сюда /api/me
- Или сюда для выхода /api/logout

## Наши собственные API токены 
- Выдаются здесь /loginApi (сам роут думаю нужно поменять в другое место)
- Выходим здесь /logoutApi
- Для входа используем password и email указанный при регистрации
- от сервера получаем token, который можно передавать как query параметр api_key либо в заголовке X-API-Key

## При настройке OAuth в личных кабинетах нужно указать RedirectURL
- Для Google /auth/google/callback
- Для Yandex /auth/yandex/callback
- Для Vkontakte /auth/vkontakte/callback
- Для MailRu /auth/mailru/callback

**Все секреты в файле .env**
