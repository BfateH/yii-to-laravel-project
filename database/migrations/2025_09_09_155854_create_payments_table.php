<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2); // 999,999,999,999.99
            $table->string('currency', 3)->default('RUB');
            $table->string('status');
            $table->string('acquirer_type');
            $table->string('acquirer_payment_id')->nullable();
            $table->text('description')->nullable();
            $table->string('order_id')->nullable();

            $table->json('metadata')->nullable(); // last4, token и пр.
            $table->string('webhook_log_id')->nullable(); // Для дедупликации (ID события от провайдера)
            $table->string('idempotency_key')->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
