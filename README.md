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
eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6InRlc3QtcnNhIn0.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWUsImlhdCI6MTc1NzA2MTc4NiwiZXhwIjoxNzk5MDY1Mzg2LCJlbWFpbCI6InRlc3RAbWFpbC5ydSIsImlzcyI6Imh0dHBzOi8vdG9rZW4uZGV2IiwiYXVkIjoiYXBpOi8vZGVmYXVsdCJ9.asvXgGQxDbz0e5eyGM9L8IHnPdmT4BO2DqD-FLyIYVaH-PkG0rD4Rp4CM17aNlWrZ66qGI8kdnRMMI9S9VbLatrYDTun7H3IwN5Gkx1WCWPQJYECS_BnAGu-9jvG41QFnUjEHvhfEdmWjxHE4pp9Hyn88kuK183jGvFCaqQoeeMoOCboP0kYd92yjiU0oAy9qna8rBoOQfGMDs4gFLIBIltYntdzm1CfOSDrE-8P07X_-qXLWf_QVnJe04KXG4nadbCAidG0c57Io8yW4zYHSrRhjpPIkfRSXp_VljSqSq7h4wqA7EqTdaR3AolL2rCrNXe3NjeIAzmA7JIZNlhjeQ

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
