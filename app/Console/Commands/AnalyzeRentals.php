<?php

namespace App\Console\Commands;

use App\Models\WhatsappUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Twilio\Rest\Client;

class AnalyzeRentals extends Command
{
    protected $signature = 'analyze:rentals';
    protected $description = 'Analiza una captura de pantalla de publicaciones de alquiler en Facebook';

    public function handle()
    {
        $facebookPath = "https://www.facebook.com/groups/615188652452832";
        $filePath = base_path('posts.jpg');

        if (!file_exists($filePath)) {
            $this->error('No se encontró el archivo posts.jpg en la raíz del proyecto.');
            return Command::FAILURE;
        }

        $this->info('📤 Enviando y analizando imagen con ChatGPT...');

        $imageData = base64_encode(file_get_contents($filePath));
        $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Analizá esta captura de pantalla del grupo de Facebook y extraé los alquileres de departamentos ofrecidos por dueños o inmobiliarias.
                            De cada uno quiero un objeto JSON con 4 propiedades: 'Autor', 'Content' (seria el texto completo de la publicación), 'Fecha' y 'Caracteristicas'. Dentro de caracteristicas: ubicación, precio, ambientes, patio, estacionamiento, etc. (poniendole emojis al inicio para hacerlo visual y un salto de linea y solo las caracteristicas que sean obvias e importantes)
                            Solo incluí posts que sean ofertas de alquiler de departamentos (no comentarios, ni pedidos).
                            Respondé SOLO en JSON, como un array. Pero sin agregarle ```json sino como si fuera el contenido directo del archivo."
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => 'data:image/jpeg;base64,' . $imageData]
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

        $this->info('💾 Guardando resultado en storage/app/rental.json...');
        Storage::put('rental.json', $jsonString);

        $this->info('✅ Análisis completado con éxito.');


        // TWILIO
        $this->info('Enviando a whastapp, data de alquileres!...');
        $path = storage_path('app/private/rental.json');

        if (!File::exists($path)) {
            $this->error('❌ No existe el archivo rental.json');
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
            $fecha = $item['Fecha'] ?? 'Sin fecha';
            $caracteristicas = $item['Caracteristicas'] ?? 'Sin descripción';

            $query = rawurlencode('"' . $item['Autor'] . " " . Str::words($item['Content'], 5) . '"');
            $link = "$facebookPath/search/?q=$query";

            $mensaje .= "_" . $fecha . "_ \n";
            $mensaje .= "$caracteristicas\n\n";
            $mensaje .= "👉 Más info: $link \n";
            $mensaje .= "---\n";
        }

//        $to = 'whatsapp:' . env('WHATSAPP_TO');

        $this->info("Mensaje que se enviará: $mensaje");
        try {
            $twilio = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));

            WhatsappUser::where('active', true)->each(function ($user) use ($twilio, $mensaje) {
                $to = 'whatsapp:' . $user->phone;

                $twilio->messages->create($to, [
                    'from' => 'whatsapp:' . env('TWILIO_FROM'),
                    'body' => $mensaje,
                ]);

                $this->info('✅ Mensaje enviado por WhatsApp a: ' . $user->name . ' | ' . $user->phone);
            });

        } catch (\Exception $e) {
            $this->error('❌ Error enviando mensaje: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
