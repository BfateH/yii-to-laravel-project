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

**1. Стучимся сюда /api/auth/token-login**

- поля provider и token, в payload у токена обязательно email
- Тестировал здесь https://token.dev/

Пример токена
eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6InRlc3QtcnNhIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWUsImlhdCI6MTc1Njk4MTI3MCwiZXhwIjoxOTk4OTg0ODcxLCJlbWFpbCI6InRlc3RAbWFpbC5ydSIsImp0aSI6InRlc3QxMTFzcyJ9.syMea09cTuwCM3BEkBaQUXI3LTXjrqpVVTUYHpr_P1fV2FZY4MbHbjch6a0d68SbTWudMfDVjKD7zvxDdJqo8ajW6rDoG2wq2E0nt-J0SF5-2X39n2vWp_wA8JS0IqjSb7X4DWBPLGUK77OWSYNVePh5BJrsZPnilEMz5MxSPvnFGfndHnI1gxW75E7e7HkeFr5VLpB-orpdN8iZiHJUXNvZpzvK_DKrpOz2zb1iJJoWjkS-wPAgxoBiKk_WIxbfGL7wqqV8aspgCOI8fv1-o2_Evyut9CzEDvdmkz42NzaVJ1jcQfuOZaKZx4wFJ6PXh_TJQQpxbxo5cuIIPaBOFg

**2. Нам отвечает сервер с данными**

- берем токен из поля access_token
- сохраняем его, например в postman. Authorisation: Bearer наш_токен

**3. Можем использовать api**
- Например, для теста идем сюда /api/me
- Или сюда для выхода /api/logout
