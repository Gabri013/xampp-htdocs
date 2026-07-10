<?php

return [

    'ponte' => [
        'secret' => env('PONTE_SECRET_KEY'),
        'login_url' => env('LEGADO_LOGIN_URL', 'https://seudominio.com/modules/auth/login.php'),
    ],

];