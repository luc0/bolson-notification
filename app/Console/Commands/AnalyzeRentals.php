<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Models\WhatsappUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
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
        // Scrapear.
        $this->line('🤖 Ejecutando script de scrapeo con Node...');

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
                'html' => base_path('captures/anezca.html'), // TODO: OK!
                'url' => 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
                'postUrl' => 'https://anezcapropiedades.com.ar/propiedades' // No hace falta
            ],
            [
                'html' => base_path('captures/puntopatagonia.html'), // OK (antes: no obtiene link de la publicacion. ver html) (funca oiP https://www.inmobiliariapuntopatagonia.com.ar/p/6858071-Departamento-en-Alquiler-en-El-Bolson-El-Bols%C3%B3n,-departamento-a-200-mts-de-plaza-Pagano)
                'url' => 'https://www.inmobiliariapuntopatagonia.com.ar/Alquiler',
                'postUrl' => 'https://www.inmobiliariapuntopatagonia.com.ar/p/'
            ],
            [
                'html' => base_path('captures/puntosurpropiedades.html'), // TODO: OK (antes: no obtiene link de la publicacion. ver html) (funco con: https://puntosurpropiedades.ar/web/propiedad.php?id_propiedad=655)
                'url' => 'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=1&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=2#listado',
                'postUrl' => 'https://puntosurpropiedades.ar/web/'
            ],
            [
                'html' => base_path('captures/rioazulpropiedades.html'), // TODO: OK! (antes redirigia a la home) (funco con: https://www.rioazulpropiedades.com/p/6667459-Departamento-en-Alquiler-en-Centro-rivadavia)
                'url' => 'https://www.rioazulpropiedades.com/Buscar?operation=2&locations=40933&o=2,2&1=1',
                'postUrl' => 'https://www.rioazulpropiedades.com/'
            ],
            [
                'html' => base_path('captures/inmobiliariadelagua.html'), // TODO: OK! (antes no funcaba) (funco con: https://inmobiliariadelagua.com.ar/departamento-alquiler-casco-centrico-el-bolson/8587691)
                'url' => 'https://inmobiliariadelagua.com.ar/s/alquiler////?business_type%5B%5D=for_rent',
                'postUrl' => 'https://inmobiliariadelagua.com.ar'
            ]
        ];

        $allItems = [];

        foreach ($sites as $site) {
            if (!file_exists($site['html'])) {
                $this->error('No se encontró el archivo HTML: ' . $site['html']);
                continue;
            }

            // Check site snapshot updates
            if (!File::exists(storage_path('html_snapshots'))) {
                File::makeDirectory(storage_path('html_snapshots'));
            }

            $html = file_get_contents($site['html']);

            $snapshotPath = storage_path('html_snapshots/' . basename($site['html']));

            if (File::exists($snapshotPath)) {
                $previousHtml = file_get_contents($snapshotPath);

                if (hash('sha256', $html) === hash('sha256', $previousHtml)) {
                    $this->line('🔁 Sin cambios detectados en el HTML de: ' . $site['url']);
                    continue;
                }
            }

            // Guardamos el nuevo snapshot para futuras comparaciones
            file_put_contents($snapshotPath, $html);

            // Continua con el analyze.

            $this->line('📤 Enviando HTML a DeepSeek para analizar: ' . $site['url']);

            $html = file_get_contents($site['html']);

//            $response = Http::withToken(env('OPENAI_API_KEY'))
//                ->timeout(120)
//                ->post('https://api.openai.com/v1/chat/completions', [
//                'model' => 'gpt-4-turbo', //'gpt-4-turbo' // gpt-3.5-turbo
//                'messages' => [
//                    [
//                        'role' => 'user',
//                        'content' => `
//               Extraé todos los avisos de alquiler de departamentos del siguiente HTML.
//
//                Cada resultado debe ser un objeto con estas claves:
//                - "Content": descripción del aviso (texto completo)
//                - "Link": URL completa al aviso (si no es válida, completala con el baseUrl: {{URL_BASE}})
//                - "Caracteristicas": string con ubicación, precio, cantidad de ambientes, baños, etc., separado por saltos de línea (\n) y sin emojis.
//
//                El resultado debe ser un array JSON válido:
//                - Todo debe estar envuelto dentro de [ ]
//                - Cada objeto debe tener comillas dobles en claves y valores
//                - No incluyas texto adicional antes o después
//                - No uses etiquetas Markdown ni emojis
//
//                Ejemplo válido:
//
//                [
//                  {
//                    "Content": "Departamento amueblado con balcón",
//                    "Link": "https://example.com/propiedad/123",
//                    "Caracteristicas": "ubicación: El Bolsón\nprecio: AR$ 200000\nambientes: 2\nbaños: 1"
//                  }
//                ]
//
//                IMPORTANTE: Solo devolvé un JSON válido como el ejemplo. No agregues ningún texto ni explicación.
//                `
//                    ], // Solo respondé el array JSON sin ningún texto adicional. // Respondé solo con el array JSON sin envolverlo en \`\`\` ni ningún otro formato. No agregues texto extra ni comentarios antes o después.
//                    [
//                        'role' => 'user',
//                        'content' => $html
//                    ]
//                ],
//                'max_tokens' => 1500, // 200 x post aprox. (y tranqui)
//            ]);

            $response = Http::withToken(env('GROQ_API_KEY'))
                ->timeout(120)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'deepseek-r1-distill-qwen-32b',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => <<<EOT
                            Extraé todos los avisos de alquiler de departamentos del siguiente HTML.

                            Cada resultado debe ser un objeto con estas claves:
                            - "Content": descripción del aviso (texto completo)
                            - "Link": URL completa al aviso (si no es válida, completala con el baseUrl: {$site['postUrl']})
                            - "Caracteristicas": string con ubicación, precio, cantidad de ambientes, baños, etc., siempre separalos por saltos de línea \n. Usa Emoji al inicio de cada una.

                            El resultado debe ser un array JSON válido:
                            - Todo debe estar envuelto dentro de [ ]
                            - Cada objeto debe tener comillas dobles en claves y valores
                            - Si hay saltos de linea usa \n

                            Ejemplo válido:

                            [
                              {
                                "Content": "Departamento amueblado con balcón",
                                "Link": "https://example.com/propiedad/123",
                                "Caracteristicas": "ubicación: El Bolsón\nprecio: AR$ 200000\nambientes: 2\nbaños: 1"
                              }
                            ]

                            IMPORTANTE: No expliques lo que vas a hacer. No incluyas bloques de reflexión como <think> o análisis previos. Solo devolvé el JSON pedido. Solo devolvé un JSON válido como el ejemplo. No agregues ningún texto ni explicación.
                            EOT
                        ],
                        [
                            'role' => 'user',
                            'content' => $html
                        ]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 3000,
                ]);

            if (!$response->successful()) {
                $this->error('❌ Error de OpenAI en ' . $site['url']);
                $this->line($response->body());
                continue;
            }

            $jsonRaw = $response['choices'][0]['message']['content'];

            $this->line($jsonRaw); // no es error // TODO: borrar este

            // Limpiar el <think> del modelo de deepseek
            $jsonClean = preg_replace('/<think>.*?<\/think>/is', '', $jsonRaw);
            $jsonClean = trim($jsonClean);
            $jsonClean = preg_replace('/^```json|```$/m', '', $jsonClean);
            $modelDataResponse = json_decode($jsonClean, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('❌ JSON malformado: ' . json_last_error_msg());
                $this->line($jsonClean); // Para debug
                return;
            }

            $this->info(json_encode($modelDataResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            try {
                foreach ($modelDataResponse as $item) {
                    $item['site_url'] = $site['url'];

                    $notExistentRental = !Rental::where('source', $item['Link'])->exists();
//                    if ($notExistentRental) {
                    if (true) {
//                        Rental::create([
//                            'source' => $item['Link'],
//                            'content' => $item['Content'],
//                            'description' => $item['Caracteristicas'],
//                        ]);

//                        $this->info('✅ Datos guardados con éxito en la DB.');

                        $allItems[] = $item; // solo nuevos
                    }
                }
            } catch (\Throwable $e) {
                $this->error('❌ Error parseando o guardando JSON para: ' . $site['url'] . ' ' . $e->getMessage());
            }
//            try {
//                $modelDataResponse = json_decode($modelDataResponse, true);
//
//                foreach ($modelDataResponse as &$item) {
//                    $item['site_url'] = $site['url'];
//                }
//
//                $allItems = array_merge($allItems, $modelDataResponse);
//            } catch (\Throwable $e) {
//                $this->error('❌ Error parseando JSON para: ' . $site['url']);
//            }
        }

        // Guardar todo en rental.json
        $this->line('💾 Guardando resultados combinados...');
        Storage::put('rental.json', json_encode($allItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('✅ Datos guardados con éxito a un json.');

        // TWILIO

        if (empty($allItems)) {
            $this->warn('⚠️ No hay alquileres para enviar.');
            return;
        }

        $mensaje = "🏠 *Alquileres disponibles:*\n\n";

        foreach ($allItems as $item) {
            $caracteristicas = $item['Caracteristicas'] ?? 'Sin descripción';
            $fuente = $item['Link'] ?? $item['site_url'] ?? 'Sin link';

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
