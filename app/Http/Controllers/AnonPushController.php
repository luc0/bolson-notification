<?php
namespace App\Http\Controllers;

use App\Models\AnonSubscriber;
use App\Notifications\NewRentalsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnonPushController extends Controller
{
    protected function currentGuest(): AnonSubscriber
    {
        // guardá un UUID en sesión (o en una cookie si preferís)
        $uuid = session('guest_uuid');
        if (!$uuid) {
            $uuid = (string) Str::uuid();
            session(['guest_uuid' => $uuid]);
        }
        return AnonSubscriber::firstOrCreate(['uuid' => $uuid]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint'    => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        $guest = $this->currentGuest();

        // 👇 esto completa subscribable_type / subscribable_id
        $guest->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $request->input('contentEncoding', 'aes128gcm')
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['endpoint' => 'required|string']);
        $guest = $this->currentGuest();
        $guest->deletePushSubscription($request->input('endpoint'));
        return response()->json(['ok' => true]);
    }

    public function test(Request $request)
    {
        $notification = new NewRentalsNotification(
            $request->input('title', 'Prueba'),
            $request->input('body', 'Llegó la notificación 🎉'),
            $request->input('url', url('/')),
            $request->input('icon', '/icons/icon-192.png'),
        );

        $notifiedCount = 0;
        AnonSubscriber::whereHas('pushSubscriptions')->cursor()->each(function ($subscriber) use ($notification, &$notifiedCount) {
            $subscriber->notify($notification);
            $notifiedCount++;
        });

        return response()->json(['ok' => true, 'notified' => $notifiedCount]);
    }
}
