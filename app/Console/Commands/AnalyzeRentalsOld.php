<?php

namespace App\Console\Commands;

use App\Models\WhatsappUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Twilio\Rest\Client;

class AnalyzeRentalsOld extends Command
{
    protected $signature = 'deprecated:analyze:rentals';
    protected $description = 'Analiza una captura de pantalla de publicaciones de alquiler en Facebook';

    public function handle()
    {
        // SCRAP FACEBOOK (old)

        $this->info('🤖 Ejecutando script de scrapeo con Node...');

        $process = new Process(['node', base_path('scrape.cjs')]);
        $process->setTimeout(120); // 2 minutos como máximo

        try {
            $process->mustRun();
            $this->info("✅ Scrapeo finalizado");
        } catch (ProcessFailedException $e) {
            $this->error('❌ Error al ejecutar el script de scrapeo:');
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        // ANALYZE

        $sites = [
            [
                'image' => base_path('captures/anezca_full.jpg'),
                'url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order='
            ],
            [
                'image' => base_path('captures/puntopatagonia_full.jpg'),
                'url' => 'https://www.inmobiliariapuntopatagonia.com.ar/Alquiler'
            ],
            [
                'image' => base_path('captures/puntosurpropiedades_full.jpg'),
                'url' => 'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=1&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=2#listado'
            ],
            [
                'image' => base_path('captures/rioazulpropiedades_full.jpg'),
                'url' => 'https://www.rioazulpropiedades.com/Buscar?operation=2&locations=40933&o=2,2&1=1'
            ],
            [
                'image' => base_path('captures/inmobiliariadelagua_full.jpg'),
                'url' => 'https://inmobiliariadelagua.com.ar/s/alquiler////?business_type%5B%5D=for_rent'
            ]
        ];

        $allItems = [];

        foreach ($sites as $site) {
            if (!file_exists($site['image'])) {
                $this->error('No se encontró la imagen: ' . $site['image']);
                continue;
            }

            $this->info('📤 Enviando imagen a OpenAI para analizar: ' . $site['image']);

            $imageData = base64_encode(file_get_contents($site['image']));

            $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4-turbo',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => "Analizá esta captura de pantalla del sitio de la inmobiliaria y extraé los alquileres de departamentos ofrecidos por dueños o inmobiliarias.
                        De cada uno quiero un objeto JSON con 2 propiedades: 'Content' (texto completo de la publicación) y 'Caracteristicas'. Dentro de Caracteristicas: ubicación, precio, ambientes, patio, estacionamiento, etc. (con emojis al inicio, salto de línea al final, todo en un string evitando arrays)
                        Solo incluí posts que sean ofertas de alquiler de departamentos y evita los que dicen 'reservado' o 'alquilado'.
                        Respondé SOLO en JSON como un array. No pongas ```json ni texto adicional."
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
                $this->error('❌ Error de OpenAI en ' . $site['url']);
                $this->line($response->body());
                continue;
            }

            $jsonString = $response['choices'][0]['message']['content'];

            try {
                $parsed = json_decode($jsonString, true);

                foreach ($parsed as &$item) {
                    $item['Fuente'] = $site['url'];
                }

                $allItems = array_merge($allItems, $parsed);
            } catch (\Throwable $e) {
                $this->error('❌ Error parseando JSON para: ' . $site['url']);
            }
        }

        // Guardar todo en rental.json
        $this->info('💾 Guardando resultados combinados...');
        Storage::put('rental.json', json_encode($allItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('✅ Datos guardados con éxito.');

        // TWILIO

        if (empty($allItems)) {
            $this->warn('⚠️ No hay alquileres para enviar.');
            return;
        }

        $mensaje = "🏠 *Alquileres disponibles:*\n\n";

        foreach ($allItems as $item) {
            $caracteristicas = $item['Caracteristicas'] ?? 'Sin descripción';
            $fuente = $item['Fuente'] ?? 'Sin link';

            $mensaje .= "$caracteristicas\n";
            $mensaje .= "👉 Ver en: $fuente\n";
            $mensaje .= "---\n";
        }

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
