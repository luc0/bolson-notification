<?php

namespace App\Services;

use App\Contracts\IWhatsapp;
use App\Models\WhatsappUser;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioService implements IWhatsapp
{
    public function sendMessage(array $allItems)
    {
        Log::info('Utilizando Twilio para envio de mensajes.');
        $bloques = [];
        $mensajeActual = "🏠 *Alquileres disponibles:*\n\n";

        foreach ($allItems as $item) {
            $caracteristicas = $item['Caracteristicas'] ?? 'Sin descripción';
            $fuente = $item['Link'] ?? $item['site_url'] ?? 'Sin link';

            $itemTexto = "$caracteristicas\n👉 Ver en: $fuente\n---\n";

            // Si agregar este item supera el límite, se guarda el mensaje actual y se comienza uno nuevo
            if (strlen($mensajeActual . $itemTexto) > 1600) {
                $bloques[] = rtrim($mensajeActual);
                $mensajeActual = ""; // sin el título en los siguientes bloques
            }

            $mensajeActual .= $itemTexto;
        }

        // Guardar el último bloque si quedó contenido sin enviar
        if (!empty(trim($mensajeActual))) {
            $bloques[] = rtrim($mensajeActual);
        }

        Log::info("Se generaron " . count($bloques) . " bloque(s) de mensaje(s).");

        try {
            $twilio = new Client(config('services.twilio.sid'), config('services.twilio.token'));

            WhatsappUser::where('active', true)->each(function ($user) use ($twilio, $bloques) {
                $to = 'whatsapp:' . $user->phone;

                foreach ($bloques as $index => $mensaje) {
                    $twilio->messages->create($to, [
                        'from' => 'whatsapp:' . config('services.twilio.from'),
                        'body' => $mensaje,
                    ]);

                    Log::info("✅ Bloque " . ($index + 1) . " enviado a " . $user->name . " | " . $user->phone);
                    sleep(1); // Delay pequeño para evitar throttle, opcional
                }
            });

        } catch (\Exception $e) {
            Log::error('❌ Error enviando mensaje: ' . $e->getMessage());
        }
    }
}
