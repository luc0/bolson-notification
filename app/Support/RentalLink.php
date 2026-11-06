<?php

namespace App\Support;

class RentalLink
{
    /**
     * The same listing may come percent-encoded ("Caba%C3%B1a") or not ("Cabaña")
     * depending on the run, so links are always stored decoded.
     */
    public static function normalize(string $link): string
    {
        return trim(rawurldecode($link));
    }
}
