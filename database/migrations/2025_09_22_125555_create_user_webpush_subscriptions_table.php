<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_webpush_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('endpoint')->unique();
            $table->string('p256dh');
            $table->string('auth');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_webpush_subscriptions');
    }
};
