<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Instructor Account
    |--------------------------------------------------------------------------
    |
    | Created (or updated) by "php artisan db:seed" when the email and
    | password are set. Leave them empty to skip it.
    |
    */

    'instructor' => [
        'name' => env('CTF_INSTRUCTOR_NAME', 'Instructor'),
        'email' => env('CTF_INSTRUCTOR_EMAIL', ''),
        'password' => env('CTF_INSTRUCTOR_PASSWORD', ''),
    ],

];
