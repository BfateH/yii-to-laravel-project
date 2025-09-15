<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use \App\Enums\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                ->default(Role::user)
                ->constrained('moonshine_user_roles')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('partner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('email')->unique();
            $table->string('password');
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();

            // Провайдер для jwt
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();

            // ID провайдеров соц.сетей
            $table->string('google_id')->nullable();
            $table->string('yandex_id')->nullable();
            $table->string('vkontakte_id')->nullable();
            $table->string('mailru_id')->nullable();

            // Поля для логики активации
            $table->boolean('is_active')->default(true);
            $table->boolean('is_banned')->default(false);
            $table->timestamp('banned_at')->nullable();
            $table->text('ban_reason')->nullable();

            // Запрос на удаление
            $table->timestamp('delete_requested_at')->nullable(); // Дата запроса удаления
            $table->string('delete_confirmation_token')->nullable(); // Токен на удаление

            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
