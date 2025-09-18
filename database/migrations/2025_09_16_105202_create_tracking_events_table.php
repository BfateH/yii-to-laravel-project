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
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();

            $table->timestamp('operation_date')->nullable();
            $table->integer('operation_type_id')->nullable();
            $table->string('operation_type_name')->nullable();
            $table->integer('operation_attr_id')->nullable();
            $table->string('operation_attr_name')->nullable();

            $table->string('operation_address_index')->nullable();
            $table->text('operation_address_description')->nullable();

            $table->string('destination_address_index')->nullable();
            $table->text('destination_address_description')->nullable();

            $table->integer('country_oper_id')->nullable();
            $table->string('country_oper_code2a')->nullable(); // Code2A
            $table->string('country_oper_code3a')->nullable(); // Code3A
            $table->string('country_oper_name_ru')->nullable(); // NameRU
            $table->string('country_oper_name_en')->nullable(); // NameEN

            $table->string('item_barcode')->nullable();
            $table->unsignedInteger('item_mass')->nullable(); // Mass в граммах

            $table->unsignedBigInteger('payment')->nullable(); // Payment (наложенный платеж)
            $table->unsignedBigInteger('value')->nullable(); // Value (объявленная ценность)
            $table->json('raw_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
