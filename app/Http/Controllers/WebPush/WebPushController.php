<?php

namespace App\Http\Controllers\WebPush;

use App\Http\Controllers\Controller;
use App\Models\UserWebPushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WebPushController extends Controller
{
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            UserWebPushSubscription::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'endpoint' => $request->input('endpoint')
                ],
                [
                    'p256dh' => $request->input('keys.p256dh'),
                    'auth' => $request->input('keys.auth')
                ]
            );

            $this->enableWebPushSubscription($user->id);

            Log::info('WebPush subscription saved', [
                'user_id' => $user->id,
                'endpoint' => $request->input('endpoint')
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('WebPush subscription error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json(['error' => 'Failed to save subscription'], 500);
        }
    }

    public function unsubscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Удаляем подписку
            UserWebPushSubscription::where('user_id', $user->id)
                ->where('endpoint', $request->input('endpoint'))
                ->delete();

            Log::info('WebPush subscription removed', [
                'user_id' => $user->id,
                'endpoint' => $request->input('endpoint')
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('WebPush unsubscription error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json(['error' => 'Failed to remove subscription'], 500);
        }
    }

    protected function enableWebPushSubscription(int $userId): void
    {
        $channel = \App\Models\Channel::where('name', 'webpush')->first();

        if ($channel) {
            \App\Models\Subscription::updateOrCreate(
                [
                    'user_id' => $userId,
                    'channel_id' => $channel->id
                ],
                [
                    'subscribed' => true
                ]
            );
        }
    }

    public function publicKey()
    {
        return response()->json([
            'publicKey' => config('services.webpush.public_key')
        ]);
    }
}
