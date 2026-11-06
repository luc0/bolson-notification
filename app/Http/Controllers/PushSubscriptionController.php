<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Models\Subscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use App\Domains\Subscriptions\Services\NewRentalsNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        // If this browser was subscribed by another member, the subscription moves to the current one.
        Subscriber::forUser($request->user())->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $request->input('contentEncoding', 'aes128gcm'),
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);

        Subscriber::forUser($request->user())->deletePushSubscription($request->input('endpoint'));

        return response()->json(['ok' => true]);
    }

    /**
     * Local only (see routes/web.php): sends a test push to every subscriber.
     */
    public function test(Request $request, NewRentalsNotifier $notifier): JsonResponse
    {
        $notification = new NewRentalsNotification(
            title: $request->input('title', 'Prueba'),
            body: $request->input('body', 'Llegó la notificación 🎉'),
            url: $request->input('url', url('/')),
            icon: $request->input('icon', '/icons/icon-192.png'),
        );

        return response()->json(['ok' => true, 'notified' => $notifier->notifyAll($notification)]);
    }
}
