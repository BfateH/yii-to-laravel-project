<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency_code', 3); // Код базовой валюты
            $table->string('target_currency_code', 3); // Код целевой валюты
            $table->decimal('rate', 20, 10); // Курс
            $table->date('date'); // Дата курса
            $table->timestamps();

            // Индексы для ускорения поиска
            $table->index(['base_currency_code', 'target_currency_code', 'date']);
            $table->unique(['base_currency_code', 'target_currency_code', 'date']); // Уникальность пары на дату
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
