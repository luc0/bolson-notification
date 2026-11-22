<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Sources\Data\Source;

/**
 * Extracts the houses for sale of a house listing page capture.
 */
class HouseListingExtractor extends AiListingExtractor
{
    protected function itemsKey(): string
    {
        return 'houses';
    }

    protected function reasoningEffort(): string
    {
        return config('services.groq.house_reasoning_effort');
    }

    protected function systemPrompt(): string
    {
        return <<<'EOT'
            You are a data extraction assistant for a public real-estate listings aggregator.
            The user gives you the HTML of a public listings page from a real-estate agency in Argentina.
            Your only job is to extract the houses for sale that appear on that page into structured JSON.
            This is public, non-personal information; always complete the extraction.
            EOT;
    }

    protected function userPrompt(Source $source): string
    {
        return <<<EOT
            Extraé todos los avisos de casas en venta (casas, cabañas, dúplex, chalets) del siguiente HTML.
            La página ya es un listado de casas en venta: incluí todos los avisos aunque no digan "venta".
            Omití los que digan explícitamente que son en alquiler, los vendidos o reservados, los que no tienen un link propio (no inventes URLs) y los que sean solo terrenos, departamentos o locales.

            Para cada aviso:
            - "Content": título del aviso. Si no tiene título, el comienzo de la descripción (máximo 150 caracteres). No copies la descripción completa.
            - "Link": URL completa al aviso. Si es relativa, completala con el baseUrl: {$source->linkBaseUrl}
            - "Caracteristicas": solo datos extra que NO estén en los otros campos (ej: servicios, cochera, calefacción, vista, escritura, financiación, apta crédito). Una por línea (separadas por \\n), cada una empezando con un emoji. No incluyas precio, ubicación, dormitorios, baños ni superficies. Omití los datos sin valor (null, N/A, 0). String vacío si no hay ninguno.
            - "Price": precio de venta con la moneda, tal como aparece (ej: "U\$S 120.000", "$95.000.000", "Consultar"). null si no figura.
            - "Currency": "USD" si el precio es en dólares (U\$S, USD, US\$), "ARS" si es en pesos, null si no hay precio.
            - "Location": ubicación de la casa.
            - "Bedrooms": cantidad de dormitorios. null si no se especifica.
            - "Bathrooms": cantidad de baños. null si no se especifica.
            - "CoveredArea": superficie cubierta (construida) en m², como número. null si no figura o es 0.
            - "TotalArea": superficie del terreno en m², como número. Si está en hectáreas, convertila (1 ha = 10000 m²). null si no figura o es 0.
            - "Image": el src de la foto principal del aviso, tal cual aparece (ej: "#img-12"). No íconos ni logos. null si no hay.
            EOT;
    }

    protected function itemSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableInteger = ['type' => ['integer', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'Content' => ['type' => 'string'],
                'Link' => ['type' => 'string'],
                'Caracteristicas' => ['type' => 'string'],
                'Price' => $nullableString,
                'Currency' => ['type' => ['string', 'null'], 'enum' => ['ARS', 'USD', null]],
                'Location' => $nullableString,
                'Bedrooms' => $nullableInteger,
                'Bathrooms' => $nullableInteger,
                'CoveredArea' => $nullableNumber,
                'TotalArea' => $nullableNumber,
                'Image' => $nullableString,
            ],
            'required' => ['Content', 'Link', 'Caracteristicas', 'Price', 'Currency', 'Location', 'Bedrooms', 'Bathrooms', 'CoveredArea', 'TotalArea', 'Image'],
            'additionalProperties' => false,
        ];
    }
}
