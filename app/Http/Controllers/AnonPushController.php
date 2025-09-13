<?php
// app/Http/Controllers/AnonPushController.php
namespace App\Http\Controllers;

use App\Models\AnonSubscriber;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

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
//        dd('ok');
        $title = $request->input('title', 'Prueba');
        $body  = $request->input('body',  'Llegó la notificación 🎉');
        $url   = $request->input('url',   url('/'));
        $icon  = $request->input('icon',  '/icons/icon-icon-192.png'); // asegurate que exista

        // Notificación inline (no requiere crear clase aparte)
        $notification = new class($title, $body, $url, $icon) extends Notification {
            public function __construct(public $t, public $b, public $u, public $i) {}
            public function via($notifiable) { return [WebPushChannel::class]; }
            public function toWebPush($notifiable, $notification)
            {
                return (new WebPushMessage)
                    ->title($this->t)
                    ->body($this->b)
                    ->icon($this->i)
                    ->badge($this->i)
                    ->data(['url' => $this->u])
                    ->action('Ver', $this->u);
            }
        };

        $count = 0;
        AnonSubscriber::whereHas('pushSubscriptions')->cursor()->each(function ($guest) use ($notification, &$count) {
            $guest->notify($notification);
            $count++;
        });

        return response()->json(['ok' => true, 'notified' => $count]);
    }
}
