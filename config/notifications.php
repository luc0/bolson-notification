<?php

return [
    'new_rentals' => [
        'title' => 'Nuevos alquileres disponibles!',
        'body_template' => 'Hoy hay {count} alquileres nuevos!',
        'url' => '/',
        'icon' => '/icons/icon-192.png',
    ],

    // {date}: expiry date (dd/mm).
    'expiry_reminder' => [
        'title' => 'Tu suscripción vence pronto',
        'body_template' => 'Tu suscripción a Techito vence el {date}. Pedinos renovarla para seguir recibiendo los avisos de alquileres nuevos.',
        'url' => '/profile',
        'icon' => '/icons/icon-192.png',
    ],
];
