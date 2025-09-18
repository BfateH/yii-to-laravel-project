<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('ru_name');
            $table->string('code');

            $table->timestamps();
        });

        $countriesJson = database_path('countries.json');
        if (file_exists($countriesJson)) {
            $countries = json_decode(file_get_contents($countriesJson), true);
            $countriesToInsert = [];

            foreach ($countries as $country) {
                $countriesToInsert[] = [
                    'name' => $country['name'],
                    'ru_name' => $country['ru_name'],
                    'code' => $country['code'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            if (!empty($countriesToInsert)) {
                DB::table('countries')->insert($countriesToInsert);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
