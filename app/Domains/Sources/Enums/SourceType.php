<?php

namespace App\Domains\Sources\Enums;

enum SourceType: string
{
    // Publishes only its own properties.
    case Agency = 'agency';

    // Aggregates listings from many publishers (Properati, MercadoLibre): may repeat rentals from other sources.
    case Portal = 'portal';
}
