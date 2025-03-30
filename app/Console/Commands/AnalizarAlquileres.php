<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Twilio\Rest\Client;

class AnalizarAlquileres extends Command
{
    protected $signature = 'analizar:alquileres';
    protected $description = 'Analiza una captura de pantalla de publicaciones de alquiler en Facebook';

    public function handle()
    {
        $filePath = base_path('posts.jpg');

        if (!file_exists($filePath)) {
            $this->error('No se encontró el archivo posts.jpg en la raíz del proyecto.');
            return Command::FAILURE;
        }

        $this->info('📤 Enviando imagen a ChatGPT Vision...');

        $imageData = base64_encode(file_get_contents($filePath));
        $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Analizá esta captura de pantalla del grupo de Facebook y extraé los alquileres de departamentos ofrecidos por dueños o inmobiliarias. De cada uno quiero un objeto JSON con:
- Autor\n- Fecha\n- 'Características relevantes' (ubicación, precio, habitaciones, etc. poniendole emojis al inicio para hacerlo visual y un enter). Solo incluí lo que sea obvio e importante.
Solo incluí posts que sean ofertas de alquiler de departamentos (no comentarios ni pedidos). Respondé SOLO en JSON, como un array. Pero sin agregarle ```json sino como si fuera el contenido directo del archivo"
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:image/jpeg;base64,' . $imageData
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 1500,
        ]);

        if (!$response->successful()) {
            $this->error('❌ Error de la API de OpenAI:');
            $this->line($response->body());
            return Command::FAILURE;
        }

        $jsonString = $response['choices'][0]['message']['content'];

        $this->info('💾 Guardando resultado en storage/app/alquileres.json...');
        Storage::put('alquileres.json', $jsonString);

        $this->info('✅ Análisis completado con éxito.');


        // TWILIO
        $this->info('Enviando a whastapp, data de alquileres!...');
        $path = storage_path('app/private/alquileres.json');

        if (!File::exists($path)) {
            $this->error('❌ No existe el archivo alquileres.json');
            return;
        }

        $data = json_decode(File::get($path), true);

        if (!$data || !is_array($data)) {
            $this->error('❌ El archivo no tiene un formato válido');
            return;
        }

        if (empty($data)) {
            $this->warn('⚠️ No hay alquileres para enviar.');
            return;
        }

        $mensaje = "🏠 *Alquileres disponibles:*\n\n";

        foreach ($data as $item) {
            $autor = $item['Autor'] ?? 'Desconocido';
            $fecha = $item['Fecha'] ?? 'Sin fecha';
            $caracteristicas = $item['Características relevantes'] ?? 'Sin descripción';

            $mensaje .= "👤 *$autor* - $fecha \n";
            $mensaje .= "$caracteristicas\n\n";
        }

        $to = 'whatsapp:' . env('WHATSAPP_TO');

        try {
            $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));

            $twilio->messages->create($to, [
                'from' => 'whatsapp:' . env('TWILIO_FROM'),
                'body' => $mensaje,
            ]);

            $this->info('✅ Mensaje enviado por WhatsApp.');
        } catch (\Exception $e) {
            $this->error('❌ Error enviando mensaje: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
