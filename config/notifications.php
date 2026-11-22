<?php

return [
    'new_rentals' => [
        // {count}: how many new rentals. body_template_one is used when there is just one.
        'title' => 'Alquileres nuevos en la Comarca',
        'body_template' => '{count} alquileres nuevos.',
        'body_template_one' => '1 alquiler nuevo.',
        'url' => '/',
        'icon' => '/icons/icon-192.png',
    ],

    // {date}: expiry date (dd/mm).
    'expiry_reminder' => [
        'title' => 'Tu suscripción vence pronto',
        'body_template' => 'Tu suscripción a Techito vence el {date}. Pedinos renovarla para seguir recibiendo avisos.',
        'url' => '/profile',
        'icon' => '/icons/icon-192.png',
    ],
];
