<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MoonShine\Laravel\Models\MoonshineUserRole;
use \App\Enums\Role;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('moonshine_user_roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('moonshine_user_roles')->insert([
            'id' => Role::admin,
            'name' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('moonshine_user_roles')->insert([
            'id' => Role::user,
            'name' => 'user',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('moonshine_user_roles')->insert([
            'id' => Role::partner,
            'name' => 'partner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moonshine_user_roles');
    }
};
