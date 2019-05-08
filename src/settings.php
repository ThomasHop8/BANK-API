<?php
return [
    'settings' => [
        'displayErrorDetails' => true,
        'addContentLengthHeader' => false,

        'app' => [
            'name' => 'BANK',
            'url' => 'https://hoekbank.tk',
            'env' => 'development',
        ],

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

        'db' => [
            'host' => '127.0.0.1',
            'dbname' => '',
            'user' => '',
            'pass' => ''
        ],

        'jwt' => [
            'secret' => '',
            'secure' => true,
            "header" => "",
            "regexp" => "",
            'passthrough' => ['']
        ],
    ],
];
