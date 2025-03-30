<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Twilio\Rest\Client;

class BuscarAlquileres extends Command
{
    protected $signature = 'buscar:alquileres';
    protected $description = 'Scrapea el grupo de Facebook, busca alquileres y los envía por WhatsApp';

    public function handle()
    {
        $this->info('Ejecutando scraper...');
        $result = Process::run('node scrape.cjs');

        if (!$result->successful()) {
            $this->error('Error al ejecutar el script de scraping.');
            return 1;
        }

        $this->info('Leyendo publicaciones...');
        $posts = json_decode(file_get_contents('posts.json'), true);

        if (!$posts || !is_array($posts)) {
            $this->error('No se pudieron leer las publicaciones.');
            return 1;
        }

        $this->info('Filtrando y parseando publicaciones...');
        $alquileres = collect($posts)->filter(function ($text) {
            return Str::contains(Str::lower($text), ['alquilo', 'alquilo departamento', 'departamento en alquiler']);
        })->map(function ($text) {
            preg_match('/\$?\s?(\d{4,6})/', $text, $priceMatch);
            preg_match('/(\d+)\s*(ambiente|ambientes)/i', $text, $roomsMatch);

            return [
                'title' => Str::limit($text, 100),
                'price' => $priceMatch[1] ?? '¿?',
                'ambientes' => $roomsMatch[1] ?? '¿?',
                'link' => 'https://www.facebook.com/groups/615188652452832',
            ];
        });

        if ($alquileres->isEmpty()) {
            $this->warn('No se encontraron alquileres para enviar.');
            return 0;
        }

        $this->info('Enviando por WhatsApp...');

        $message = $alquileres->map(function ($item) {
            return "\uD83D\uDCCC {$item['title']}\n\uD83D\uDCB0 {$item['price']} - \uD83C\uDFE5 {$item['ambientes']} ambientes\n\uD83D\uDD17 {$item['link']}";
        })->implode("\n\n");

        $this->sendWhatsApp($message);
        $this->info('Mensaje enviado con éxito \uD83D\uDCF2');

        return 0;
    }

    protected function sendWhatsApp($message)
    {
        $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));

        $twilio->messages->create(
            'whatsapp:' . env('WHATSAPP_TO'),
            [
                'from' => 'whatsapp:' . env('TWILIO_WHATSAPP_FROM'),
                'body' => $message
            ]
        );
    }
}
