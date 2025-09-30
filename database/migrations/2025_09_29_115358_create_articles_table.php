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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('article_categories')->cascadeOnDelete();
            $table->foreignId('copied_from_article_id')->nullable()->constrained('articles')->nullOnDelete();

            $table->boolean('is_published')->default(false);
            $table->string('title');
            $table->string('slug');
            $table->text('content')->nullable();
            $table->integer('sort_index')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
