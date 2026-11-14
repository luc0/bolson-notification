<?php

return [
    // Password of the members the admin registers from the Telegram bot (/registrar <phone>).
    // They should change it from the profile.
    'initial_password' => env('MEMBER_INITIAL_PASSWORD', '12345'),
];
