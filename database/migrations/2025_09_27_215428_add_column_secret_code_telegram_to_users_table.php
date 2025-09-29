<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('secret_code_telegram')->nullable()->unique();
        });

        foreach (DB::table('users')->cursor() as $user) {
            do {
                $token = 'secret_token_' . Str::random(128);
                $exists = DB::table('users')
                    ->where('secret_code_telegram', $token)
                    ->exists();
            } while ($exists);

            DB::table('users')
                ->where('id', $user->id)
                ->update(['secret_code_telegram' => $token]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_secret_code_telegram_unique');
            $table->dropColumn('secret_code_telegram');
        });
    }
};
