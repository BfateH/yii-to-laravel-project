<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_with_vpn')->default(false);

            $table->string('description')->nullable();
            $table->string('link_to_the_store')->nullable();
            $table->string('logo_preview')->nullable();

            // Сортировка
            $table->integer('popularity_index')->default(0);
            $table->integer('rating_index')->default(0);
            $table->integer('sort_index')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
