<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateWebPushVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';
    protected $description = 'Generate a VAPID key pair for browser push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('WEB_PUSH_VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('WEB_PUSH_VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->line('WEB_PUSH_VAPID_SUBJECT=mailto:support@lyt.com.ng');
        return self::SUCCESS;
    }
}