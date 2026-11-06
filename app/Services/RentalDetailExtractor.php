<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Extracts structured data from the text of a listing's detail page.
 */
class RentalDetailExtractor
{
    public const SERVICES = [
        'electricity', 'running_water', 'natural_gas', 'bottled_gas', 'sewer', 'internet', 'cable', 'phone', 'paving',
    ];

    private const SYSTEM_PROMPT = <<<EOT
        You are a data extraction assistant for a public real-estate listings aggregator in Argentina.
        You receive the text of ONE property detail page. Extract only data explicitly stated for that property.
        Never guess or infer missing data: use null (or an empty list) when something isn't stated.
        Ignore search filters, menus, sidebars and other properties listed on the page (e.g. a "Servicios" search filter).
        EOT;

    private const USER_PROMPT = <<<EOT
        Extraé los datos de esta propiedad:
        - "CoveredArea": superficie cubierta en m² (número). null si no figura o es 0.
        - "TotalArea": superficie total del terreno/lote en m² (número). null si no figura o es 0.
        - "Rooms": cantidad de ambientes. null si no figura.
        - "Bedrooms": cantidad de dormitorios. null si no figura. Un monoambiente tiene 0 dormitorios solo si lo dice.
        - "Bathrooms": cantidad de baños. null si no figura o es 0.
        - "ParkingSpaces": cantidad de cocheras/garajes de la propiedad. "Entrada de auto" o "lugar para estacionar" sin cochera no cuenta. null si no figura.
        - "AgeYears": antigüedad de la construcción en años, solo si dice "antigüedad" o "a estrenar" (0 si dice "a estrenar"). NO es la duración del contrato ("alquiler por 2 años"). null si no figura.
        - "Services": servicios de la propiedad, de la sección de servicios o de la descripción. Solo los mencionados explícitamente:
          electricity (luz/electricidad), running_water (agua corriente/agua de red), natural_gas (gas natural),
          bottled_gas (gas envasado/garrafa), sewer (cloacas), internet (internet/wifi), cable (cable/videocable),
          phone (teléfono), paving (pavimento). "Todos los servicios" sin detallar no cuenta.

        Texto de la página:
        EOT;

    /**
     * Words that must appear on the page for a value to be accepted: a cheap guard against invented data.
     */
    private const EVIDENCE_PATTERNS = [
        'CoveredArea' => '/m²|m2|mts|metros|\bm\b|superficie|sup\.? cubierta/iu',
        'TotalArea' => '/m²|m2|mts|metros|\bm\b|\bha\b|hect[áa]rea|superficie|sup\.? total/iu',
        'Rooms' => '/ambiente/iu',
        'Bedrooms' => '/dormitorio|habitaci[óo]n|monoambiente/iu',
        'Bathrooms' => '/baño/iu',
        'ParkingSpaces' => '/cochera|garaje|garage|estacionamiento/iu',
        'AgeYears' => '/antig[üu]edad|estrenar/iu',
    ];

    public function __construct(private readonly GroqClient $groq)
    {
    }

    /**
     * @return array|null null when the AI call fails.
     */
    public function extract(string $pageText, string $url): ?array
    {
        $response = $this->groq->chat([
            'model' => config('services.groq.model'),
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => self::USER_PROMPT . "\n" . $pageText],
            ],
            'temperature' => 0,
            'reasoning_effort' => config('services.groq.reasoning_effort'),
            'max_tokens' => 4096,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'rental_detail', 'strict' => true, 'schema' => $this->responseSchema()],
            ],
        ]);

        if (!$response->successful()) {
            Log::error("❌ Detail extraction failed for {$url}", ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $detail = json_decode($response['choices'][0]['message']['content'] ?? '', true);
        if (!is_array($detail)) {
            Log::error("❌ Detail extraction returned invalid JSON for {$url}");
            return null;
        }

        return $this->dropValuesWithoutEvidence($detail, $pageText);
    }

    private function dropValuesWithoutEvidence(array $detail, string $pageText): array
    {
        foreach (self::EVIDENCE_PATTERNS as $field => $pattern) {
            $hasEvidence = preg_match($pattern, $pageText) === 1;
            if (!$hasEvidence) {
                $detail[$field] = null;
            }
        }

        return $detail;
    }

    private function responseSchema(): array
    {
        $nullableNumber = ['type' => ['number', 'null']];
        $nullableInteger = ['type' => ['integer', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'CoveredArea' => $nullableNumber,
                'TotalArea' => $nullableNumber,
                'Rooms' => $nullableInteger,
                'Bedrooms' => $nullableInteger,
                'Bathrooms' => $nullableInteger,
                'ParkingSpaces' => $nullableInteger,
                'AgeYears' => $nullableInteger,
                'Services' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::SERVICES]],
            ],
            'required' => ['CoveredArea', 'TotalArea', 'Rooms', 'Bedrooms', 'Bathrooms', 'ParkingSpaces', 'AgeYears', 'Services'],
            'additionalProperties' => false,
        ];
    }
}
