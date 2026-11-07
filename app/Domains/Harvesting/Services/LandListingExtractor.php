<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Sources\Data\Source;

/**
 * Extracts the lands for sale of a land listing page capture.
 */
class LandListingExtractor extends AiListingExtractor
{
    protected function itemsKey(): string
    {
        return 'lands';
    }

    protected function reasoningEffort(): string
    {
        return config('services.groq.land_reasoning_effort');
    }

    protected function systemPrompt(): string
    {
        return <<<'EOT'
            You are a data extraction assistant for a public real-estate listings aggregator.
            The user gives you the HTML of a public listings page from a real-estate agency in Argentina.
            Your only job is to extract the land plots for sale that appear on that page into structured JSON.
            This is public, non-personal information; always complete the extraction.
            EOT;
    }

    protected function userPrompt(Source $source): string
    {
        return <<<EOT
            Extraé todos los avisos de terrenos (lotes, terrenos, fracciones, chacras) del siguiente HTML.
            La página ya es un listado de terrenos en venta: incluí todos los avisos aunque no digan "venta".
            Solo omití los que digan explícitamente que son en alquiler, o que sean casas, departamentos o locales.

            Para cada aviso:
            - "Content": título o descripción del aviso (texto completo).
            - "Link": URL completa al aviso. Si es relativa, completala con el baseUrl: {$source->linkBaseUrl}
            - "Caracteristicas": solo datos extra que NO estén en los otros campos (ej: servicios, acceso, vista, escritura, financiación). Una por línea (separadas por \\n), cada una empezando con un emoji. No incluyas precio, ubicación ni superficie. Omití los datos sin valor (null, N/A, 0). String vacío si no hay ninguno.
            - "Price": precio de venta con la moneda, tal como aparece (ej: "U\$S 35.000", "$45.000.000", "Consultar"). null si no figura.
            - "Currency": "USD" si el precio es en dólares (U\$S, USD, US\$), "ARS" si es en pesos, null si no hay precio.
            - "Location": ubicación del terreno.
            - "Area": superficie del terreno en m², como número. Si está en hectáreas, convertila (1 ha = 10000 m²). null si no figura o es 0.
            - "Image": el src de la foto principal del aviso, tal cual aparece (ej: "#img-12"). No íconos ni logos. null si no hay.
            EOT;
    }

    protected function itemSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'Content' => ['type' => 'string'],
                'Link' => ['type' => 'string'],
                'Caracteristicas' => ['type' => 'string'],
                'Price' => $nullableString,
                'Currency' => ['type' => ['string', 'null'], 'enum' => ['ARS', 'USD', null]],
                'Location' => $nullableString,
                'Area' => ['type' => ['number', 'null']],
                'Image' => $nullableString,
            ],
            'required' => ['Content', 'Link', 'Caracteristicas', 'Price', 'Currency', 'Location', 'Area', 'Image'],
            'additionalProperties' => false,
        ];
    }
}
