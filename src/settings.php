<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        // Database Settings
        // 'db' => [
        //     'host' => 'localhost',
        //     'user' => 'root',
        //     'pass' => 's3cret',
        //     'dbname' => 'sandbox_shop',
        //     'driver' => 'mysql'
        // ],

        'db' => [
            'host' => 'mysql.idhostinger.com',
            'user' => 'u590420741_sa',
            'pass' => 'rahasia411',
            'dbname' => 'u590420741_db',
            'driver' => 'mysql'
        ],

        // Mailer Settings
        /*'mailer' => [
            'username' => "8bb9747a9e628f",
            'password' => "7d57713fd0be13",
            'display_name' => "Bellezkin Contact",
            'display_email' => "contact@bellezkincare.com",
            'reply_name' => "Bellezkin Reply",
            'reply_email' => "reply@bellezkincare.com"
        ],*/

        'mailer' => [
            'username' => "affiliation@bellezkin.com",
            'password' => "bersamabellezkin",
            'display_name' => "Bellezkin Affiliation",
            'display_email' => "affiliation@bellezkin.com",
            'reply_name' => "Bellezkin No Reply",
            'reply_email' => "affiliation@bellezkin.com"
        ],

        // Rajaongkir Settings
        'rajaongkir' => [
            'api_key' => 'e923cc9c908be90c2eada981aa34d47c',
            'account_type' => 'pro'
        ]
    ],
];
