<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('related_type')->nullable();

            $table->integer('last_user_message_read')->default(0);
            $table->integer('last_admin_message_read')->default(0);

            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
