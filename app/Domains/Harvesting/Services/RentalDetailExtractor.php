<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Rentals\Enums\City;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Enums\Utility;
use App\Integrations\Groq\GroqClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Extracts structured data from the text of a listing's detail page.
 */
class RentalDetailExtractor
{
    private const SYSTEM_PROMPT = <<<'EOT'
        You are a data extraction assistant for a public real-estate listings aggregator in Argentina.
        You receive the text of ONE property detail page. Extract only data explicitly stated for that property.
        Never guess or infer missing data: use null (or an empty list) when something isn't stated.
        Ignore search filters, menus, sidebars and other properties listed on the page (e.g. a "Servicios" search filter).
        EOT;

    private const USER_PROMPT = <<<'EOT'
        Extraé los datos de esta propiedad:
        - "Title": título corto y descriptivo para un listado (máximo 70 caracteres), en español. Mayúscula solo al inicio y en nombres propios (barrios, calles).
          Que se entienda cómo es la propiedad: tipo, tamaño (preferí dormitorios; si no, ambientes o m²), lo que la distingue según la descripción (ej: amoblado, con parque, con patio, con cochera, luminoso, frente al río) y el barrio o paraje si figura.
          Abreviaturas: "Depto." es departamento, "Dpto." también.
          Usá el "Título del aviso" como dato pero reescribilo: no lo copies, y nunca en mayúsculas sostenidas.
          No pongas la ciudad (ya se muestra aparte): "en el centro" alcanza.
          Ejemplos: "Casa de 2 dormitorios con parque en Mallín Ahogado", "Monoambiente amoblado en el centro", "Local de 45 m² sobre Av. Belgrano".
          Solo con datos que figuran en la página: no agregues adjetivos ni rasgos que no estén. "A estrenar" solo si lo dice la descripción. Sin precio, sin nombre de la inmobiliaria, sin "en alquiler", sin emojis.
        - "CoveredArea": superficie cubierta en m² (número). null si no figura o es 0.
        - "TotalArea": superficie total del terreno/lote en m² (número). null si no figura o es 0.
        - "Rooms": cantidad de ambientes. null si no figura.
        - "Bedrooms": cantidad de dormitorios. null si no figura. Un monoambiente tiene 0 dormitorios solo si lo dice.
        - "Bathrooms": cantidad de baños. null si no figura o es 0.
        - "ParkingSpaces": cantidad de cocheras/garajes de la propiedad. "Entrada de auto" o "lugar para estacionar" sin cochera no cuenta. null si no figura.
        - "City": ciudad donde está la propiedad: el_bolson, lago_puelo, el_hoyo, epuyen o cholila. Solo si figura en los datos de la propiedad (no en menús ni buscadores). null si no figura o es otra.
        - "Neighborhood": barrio o paraje de la propiedad si figura (ej: "Mallín Ahogado", "Villa Turismo"). No es la calle ni la dirección. null si no figura.
        - "PropertyUse": residential si es para vivir (casa, departamento, cabaña, dúplex...) o commercial si es para un negocio (local, oficina, galpón...), según el tipo de propiedad. null si no figura.
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

    private const MAX_TITLE_LENGTH = 90;

    public function __construct(private readonly GroqClient $groq) {}

    /**
     * @return array|null null when the AI call fails.
     */
    public function extract(string $pageText, string $url): ?array
    {
        $response = $this->groq->chat([
            'model' => config('services.groq.model'),
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => self::USER_PROMPT."\n".$pageText],
            ],
            'temperature' => 0,
            'reasoning_effort' => config('services.groq.reasoning_effort'),
            'max_tokens' => 4096,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'rental_detail', 'strict' => true, 'schema' => $this->responseSchema()],
            ],
        ]);

        if (! $response->successful()) {
            Log::error("❌ Detail extraction failed for {$url}", ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $detail = json_decode($response['choices'][0]['message']['content'] ?? '', true);
        if (! is_array($detail)) {
            Log::error("❌ Detail extraction returned invalid JSON for {$url}");

            return null;
        }

        return $this->dropValuesWithoutEvidence($detail, $pageText);
    }

    private function dropValuesWithoutEvidence(array $detail, string $pageText): array
    {
        foreach (self::EVIDENCE_PATTERNS as $field => $pattern) {
            $hasEvidence = preg_match($pattern, $pageText) === 1;
            if (! $hasEvidence) {
                $detail[$field] = null;
            }
        }

        $normalizedPage = Str::of($pageText)->ascii()->lower()->squish()->toString();

        $city = City::tryFrom((string) ($detail['City'] ?? ''));
        $isCityOnPage = $city !== null && collect($city->aliases())->contains(fn (string $alias) => str_contains($normalizedPage, $alias));
        $detail['City'] = $isCityOnPage ? $city->value : null;

        $propertyUse = PropertyUse::tryFrom((string) ($detail['PropertyUse'] ?? ''));
        $isPropertyTypeOnPage = $propertyUse !== null && collect($propertyUse->keywords())->contains(fn (string $keyword) => preg_match('/\b'.preg_quote($keyword, '/').'\b/', $normalizedPage) === 1);
        $detail['PropertyUse'] = $isPropertyTypeOnPage ? $propertyUse->value : null;

        $neighborhood = trim((string) ($detail['Neighborhood'] ?? ''));
        $isNeighborhoodOnPage = $neighborhood !== '' && str_contains($normalizedPage, Str::of($neighborhood)->ascii()->lower()->squish()->toString());
        $detail['Neighborhood'] = $isNeighborhoodOnPage ? $neighborhood : null;

        $detail['Title'] = $this->cleanTitle($detail['Title'] ?? null);

        return $detail;
    }

    /**
     * A title with a price, in capitals or longer than a listing line is discarded: the agency's title is shown instead.
     */
    private function cleanTitle(?string $title): ?string
    {
        $title = Str::of((string) $title)->squish()->rtrim('.')->toString();
        $hasPrice = preg_match('/\$|\busd\b|u\$s/iu', $title) === 1;
        $isShouting = preg_match_all('/\p{Lu}/u', $title) > mb_strlen($title) / 2;
        $isUsable = $title !== '' && mb_strlen($title) <= self::MAX_TITLE_LENGTH && ! $hasPrice && ! $isShouting;
        if (! $isUsable) {
            return null;
        }

        return Str::ucfirst($title);
    }

    private function responseSchema(): array
    {
        $nullableNumber = ['type' => ['number', 'null']];
        $nullableInteger = ['type' => ['integer', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'Title' => ['type' => ['string', 'null']],
                'CoveredArea' => $nullableNumber,
                'TotalArea' => $nullableNumber,
                'Rooms' => $nullableInteger,
                'Bedrooms' => $nullableInteger,
                'Bathrooms' => $nullableInteger,
                'ParkingSpaces' => $nullableInteger,
                'AgeYears' => $nullableInteger,
                'City' => ['type' => ['string', 'null'], 'enum' => [...City::values(), null]],
                'PropertyUse' => ['type' => ['string', 'null'], 'enum' => [...PropertyUse::values(), null]],
                'Neighborhood' => ['type' => ['string', 'null']],
                'Services' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => Utility::values()]],
            ],
            'required' => ['Title', 'CoveredArea', 'TotalArea', 'Rooms', 'Bedrooms', 'Bathrooms', 'ParkingSpaces', 'AgeYears', 'City', 'Neighborhood', 'PropertyUse', 'Services'],
            'additionalProperties' => false,
        ];
    }
}
