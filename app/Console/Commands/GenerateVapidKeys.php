<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:generate-keys';
    protected $description = 'Generate VAPID keys for Web Push notifications';

    public function handle()
    {
        $keys = VAPID::createVapidKeys();

        $this->info('VAPID Keys Generated:');
        $this->line('Public Key: ' . $keys['publicKey']);
        $this->line('Private Key: ' . $keys['privateKey']);

        $this->info("\nAdd these to your .env file:");
        $this->line('WEBPUSH_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('WEBPUSH_PRIVATE_KEY=' . $keys['privateKey']);
    }
}
