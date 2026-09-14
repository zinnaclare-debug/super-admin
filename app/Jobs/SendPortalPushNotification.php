<?php

namespace App\Jobs;

use App\Models\PortalNotification;
use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendPortalPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(): void
    {
        $notification = PortalNotification::query()->find($this->notificationId);
        if (!$notification || !config('services.web_push.public_key') || !config('services.web_push.private_key')) {
            return;
        }

        $subscriptions = PushSubscription::query()->where('user_id', $notification->user_id)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.web_push.subject'),
                    'publicKey' => config('services.web_push.public_key'),
                    'privateKey' => config('services.web_push.private_key'),
                ],
            ]);
            $payload = json_encode([
                'title' => $notification->title,
                'body' => $notification->message,
                'url' => $notification->url ?: '/',
                'tag' => 'portal-notification-' . $notification->id,
                'icon' => '/pwa-192.png',
            ], JSON_UNESCAPED_SLASHES);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token],
                    'contentEncoding' => $subscription->content_encoding,
                ]), $payload);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Portal push notification was not delivered.', [
                'notification_id' => $notification->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}