<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->unique()->after('user_id');

            // Поле для хранения данных событий наложенного платежа
            $table->json('postal_order_events_data')->nullable()->after('status');

            // Поле для хранения текста последней ошибки отслеживания
            $table->text('last_tracking_error')->nullable()->after('postal_order_events_data');

            // Поле для хранения типа последней ошибки отслеживания
            $table->string('last_tracking_error_type')->nullable()->after('last_tracking_error');

            // Добавляем поле для хранения времени последнего обновления отслеживания
            $table->timestamp('last_tracking_update')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $indexName = 'packages_tracking_number_unique';
            $table->dropUnique($indexName);
            $table->dropColumn('tracking_number');
            $table->dropColumn(['last_tracking_update', 'postal_order_events_data', 'last_tracking_error', 'last_tracking_error_type']);
        });
    }
};
?>
