<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Sources\Data\Source;

/**
 * Extracts the rentals of a rental listing page capture.
 */
class ListingExtractor extends AiListingExtractor
{
    protected function itemsKey(): string
    {
        return 'rentals';
    }

    protected function systemPrompt(): string
    {
        return <<<'EOT'
            You are a data extraction assistant for a public real-estate listings aggregator.
            The user gives you the HTML of a public listings page from a real-estate agency in Argentina.
            Your only job is to extract the rental listings that appear on that page into structured JSON.
            This is public, non-personal information; always complete the extraction.
            EOT;
    }

    protected function userPrompt(Source $source): string
    {
        return <<<EOT
            Extraé todos los avisos de alquiler del siguiente HTML.

            Para cada aviso:
            - "Content": título o descripción del aviso (texto completo).
            - "Link": URL completa al aviso. Si es relativa, completala con el baseUrl: {$source->linkBaseUrl}
            - "Caracteristicas": solo datos extra que NO estén en los otros campos (ej: superficie, cochera, amoblado, temporal/permanente, mascotas). Una por línea (separadas por \\n), cada una empezando con un emoji. No incluyas precio, ubicación, ambientes ni baños. Omití los datos sin valor (null, N/A, 0, 0 m²). String vacío si no hay ninguno.
            - "Price": precio de alquiler con la moneda, tal como aparece (ej: "$650.000", "U\$S 550", "Consultar"). null si no figura.
            - "Currency": "USD" si el precio es en dólares (U\$S, USD, US\$), "ARS" si es en pesos, null si no hay precio.
            - "Location": ubicación del alquiler.
            - "Rooms": cantidad de ambientes. null si no se especifica.
            - "Bathrooms": cantidad de baños. null si no se especifica.
            - "Image": el src de la foto principal del aviso, tal cual aparece (ej: "#img-12"). No íconos ni logos. null si no hay.
            EOT;
    }

    protected function itemSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableInteger = ['type' => ['integer', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'Content' => ['type' => 'string'],
                'Link' => ['type' => 'string'],
                'Caracteristicas' => ['type' => 'string'],
                'Price' => $nullableString,
                'Currency' => ['type' => ['string', 'null'], 'enum' => ['ARS', 'USD', null]],
                'Location' => $nullableString,
                'Rooms' => $nullableInteger,
                'Bathrooms' => $nullableInteger,
                'Image' => $nullableString,
            ],
            'required' => ['Content', 'Link', 'Caracteristicas', 'Price', 'Currency', 'Location', 'Rooms', 'Bathrooms', 'Image'],
            'additionalProperties' => false,
        ];
    }
}
