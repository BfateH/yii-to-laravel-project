<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tracking_rate_limits', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tracking_rate_limits');
    }
};
