<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Models\AnonSubscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use App\Domains\Subscriptions\Services\NewRentalsNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\PushSubscription;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        // Fills push_subscriptions.subscribable_type / subscribable_id.
        $this->subscriberFor($data['endpoint'])->updatePushSubscription(
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

        PushSubscription::findByEndpoint($request->input('endpoint'))?->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Local only (see routes/api.php): sends a test push to every subscriber.
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

    /**
     * The push endpoint identifies the browser (API routes have no session): reuse its subscriber
     * when the browser subscribes again, otherwise create a new anonymous one.
     */
    private function subscriberFor(string $endpoint): AnonSubscriber
    {
        $existingSubscriber = PushSubscription::findByEndpoint($endpoint)?->subscribable;
        if ($existingSubscriber instanceof AnonSubscriber) {
            return $existingSubscriber;
        }

        return AnonSubscriber::create(['uuid' => (string) Str::uuid()]);
    }
}
