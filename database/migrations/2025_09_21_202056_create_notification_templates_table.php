<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();

            $table->string('key');
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('subject')->nullable();
            $table->text('body');

            $table->timestamps();
            $table->unique(['key', 'channel_id']);
        });

        $this->addTicketCreatedTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }

    protected function addTicketCreatedTemplates(): void
    {
        $channels = DB::table('channels')->get();
        $templates = [];
        foreach ($channels as $channel) {
            switch ($channel->name) {
                case 'email':
                    $templates[] = [
                        'key' => 'ticket_created',
                        'channel_id' => $channel->id,
                        'subject' => 'Новый тикет #{{id}}',
                        'body' => "Создан новый тикет #{{id}}.<br>Тема: {subject}<br>Ссылка: {ticket_link}",
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $templates[] = [
                        'key' => 'ticket_message_created',
                        'channel_id' => $channel->id,
                        'subject' => 'Новое сообщение в тикете #{{ticket_id}}',
                        'body' => "Новое сообщение в тикете #{{ticket_id}}<br>Сообщение: {message}",
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    break;

                case 'webpush':
                    $templates[] = [
                        'key' => 'ticket_created',
                        'channel_id' => $channel->id,
                        'subject' => 'Новый тикет #{{id}}',
                        'body' => "Создан новый тикет #{{id}}\nТема: {subject}\nСсылка: {ticket_link}",
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $templates[] = [
                        'key' => 'ticket_message_created',
                        'channel_id' => $channel->id,
                        'subject' => 'Новое сообщение в тикете #{{ticket_id}}',
                        'body' => "Новое сообщение в тикете #{{ticket_id}}\n\nСообщение: {message}",
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    break;

                case 'telegram':
                    $templates[] = [
                        'key' => 'ticket_created',
                        'channel_id' => $channel->id,
                        'subject' => null,
                        'body' => "🔔 Новый тикет #{{id}}\n\nТема: {subject}\nСсылка: {ticket_link}",
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $templates[] = [
                        'key' => 'ticket_message_created',
                        'channel_id' => $channel->id,
                        'subject' => null,
                        'body' => "🔔 Новое сообщение в тикете #{{ticket_id}}\n\nСообщение: {message}",
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    break;
            }
        }

        foreach ($templates as $template) {
            DB::table('notification_templates')->updateOrInsert(
                ['key' => $template['key'], 'channel_id' => $template['channel_id']],
                $template
            );
        }
    }

};
