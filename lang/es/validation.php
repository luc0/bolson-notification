<?php

// Messages for the rules this app uses (plus the most common ones). Missing keys fall back to English.
return [
    'accepted' => 'Tenés que aceptar :attribute.',
    'array' => ':Attribute tiene que ser una lista.',
    'boolean' => ':Attribute tiene que ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña no es correcta.',
    'date' => ':Attribute no es una fecha válida.',
    'email' => ':Attribute tiene que ser un email válido.',
    'exists' => ':Attribute no es válido.',
    'in' => ':Attribute no es válido.',
    'integer' => ':Attribute tiene que ser un número entero.',
    'max' => [
        'array' => ':Attribute no puede tener más de :max elementos.',
        'file' => ':Attribute no puede pesar más de :max kilobytes.',
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'array' => ':Attribute tiene que tener al menos :min elementos.',
        'file' => ':Attribute tiene que pesar al menos :min kilobytes.',
        'numeric' => ':Attribute tiene que ser al menos :min.',
        'string' => ':Attribute tiene que tener al menos :min caracteres.',
    ],
    'numeric' => ':Attribute tiene que ser un número.',
    'password' => [
        'letters' => ':Attribute tiene que tener al menos una letra.',
        'mixed' => ':Attribute tiene que tener al menos una mayúscula y una minúscula.',
        'numbers' => ':Attribute tiene que tener al menos un número.',
        'symbols' => ':Attribute tiene que tener al menos un símbolo.',
        'uncompromised' => 'La contraseña apareció en una filtración de datos. Elegí otra.',
    ],
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'Completá :attribute.',
    'same' => ':Attribute y :other tienen que coincidir.',
    'string' => ':Attribute tiene que ser texto.',
    'unique' => 'Ya hay una cuenta con :attribute ingresado.',
    'url' => ':Attribute tiene que ser un link válido.',

    'attributes' => [
        'current_password' => 'la contraseña actual',
        'email' => 'el email',
        'password' => 'la contraseña',
        'password_confirmation' => 'la confirmación de la contraseña',
        'phone' => 'el teléfono',
    ],
];
