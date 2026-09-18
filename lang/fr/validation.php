<?php

return array_replace_recursive(
    require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php'),
    [
        'required' => 'Le champ :attribute est obligatoire.',
        'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
        'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
        'min' => [
            'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
        ],
        'attributes' => [
            'name' => 'nom complet',
            'email' => 'adresse e-mail',
            'phone' => 'téléphone',
            'password' => 'mot de passe',
            'password_confirmation' => 'confirmation du mot de passe',
            'code' => 'code de confirmation',
        ],
    ],
);
