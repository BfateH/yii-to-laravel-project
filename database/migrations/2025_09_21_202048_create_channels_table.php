<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->boolean('enabled')->default(true);

            $table->timestamps();
        });

        $channels = [
            ['name' => 'email', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'webpush', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'telegram', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($channels as $channel) {
            DB::table('channels')->updateOrInsert(
                ['name' => $channel['name']],
                $channel
            );
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
