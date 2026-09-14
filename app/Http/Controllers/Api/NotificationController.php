<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortalNotification;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $limit = max(1, min(50, (int) $request->integer('limit', 20)));
        $query = PortalNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id');
        $items = (clone $query)
            ->limit($limit)
            ->get()
            ->map(fn (PortalNotification $item) => $this->payload($item));

        return response()->json([
            'data' => $items,
            'meta' => ['unread_count' => (clone $query)->whereNull('read_at')->count()],
        ]);
    }

    public function markRead(Request $request)
    {
        $payload = $request->validate([
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
            'all' => ['nullable', 'boolean'],
        ]);

        $query = PortalNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at');
        if (!($payload['all'] ?? false)) {
            $query->whereIn('id', $payload['ids'] ?? []);
        }
        $query->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.']);
    }

    public function publicKey()
    {
        $key = (string) config('services.web_push.public_key');
        if ($key === '') {
            return response()->json(['message' => 'Device notifications are not configured yet.'], 503);
        }

        return response()->json(['data' => ['public_key' => $key]]);
    }

    public function subscribe(Request $request)
    {
        $payload = $request->validate([
            'endpoint' => ['required', 'url', 'max:4000'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:1000'],
            'keys.auth' => ['required', 'string', 'max:1000'],
            'content_encoding' => ['nullable', Rule::in(['aes128gcm', 'aesgcm'])],
        ]);
        $hash = hash('sha256', $payload['endpoint']);

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $payload['endpoint'],
                'public_key' => $payload['keys']['p256dh'],
                'auth_token' => $payload['keys']['auth'],
                'content_encoding' => $payload['content_encoding'] ?? 'aes128gcm',
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['message' => 'Device notifications enabled.']);
    }

    public function unsubscribe(Request $request)
    {
        $payload = $request->validate(['endpoint' => ['required', 'url', 'max:4000']]);
        PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $payload['endpoint']))
            ->delete();

        return response()->json(['message' => 'Device notifications disabled.']);
    }

    private function payload(PortalNotification $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'message' => $item->message,
            'url' => $item->url,
            'read_at' => $item->read_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }
}