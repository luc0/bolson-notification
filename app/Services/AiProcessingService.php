<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiProcessingService
{
    public function process($site, $html)
    {
        $response = Http::withToken(config('services.groq.api_key'))
            ->timeout(120)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
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
                'max_tokens' => 8192, // 8192, creo que es el limite del modelo. En anezca uso 3.100
            ]);

        if (!$response->successful()) {
            Log::error('❌ Error de IA en ' . $site['url']);
            Log::info($response->body());
            // TODO: hacer un continue
//            continue;
        }

        $jsonRaw = $response['choices'][0]['message']['content'];

        // Limpiar el <think> del modelo de deepseek
        $jsonClean = preg_replace('/<think>.*?<\/think>/is', '', $jsonRaw);
        $jsonClean = trim($jsonClean);
        $jsonClean = preg_replace('/^```json|```$/m', '', $jsonClean);
        $modelDataResponse = json_decode($jsonClean, true);

        Log::info($jsonClean);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('❌ JSON malformado: ' . json_last_error_msg());
            return;
        }

        return $modelDataResponse;
    }
}
