<?php
return [
    'app_name' => getenv('APP_NAME') ?: 'MomoBoard',
    'base_url' => rtrim(getenv('APP_BASE_URL') ?: '', '/'),
    'environment' => getenv('APP_ENV') ?: 'production',
    'storage_path' => __DIR__ . '/../storage',
    'upload_path' => __DIR__ . '/../public/uploads',
    'data_driver' => getenv('DATA_DRIVER') ?: 'json',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Seoul',
    'max_upload_mb' => (int) (getenv('MAX_UPLOAD_MB') ?: 5),
    'boards' => [
        'b' => [
            'key' => 'b',
            'title' => 'Bubble',
            'subtitle' => '자유롭게 이미지와 이야기를 올리는 메인 보드',
            'accent' => 'strawberry',
        ],
        'g' => [
            'key' => 'g',
            'title' => 'Gadget',
            'subtitle' => '기술, 개발, 장비 이야기를 모아두는 보드',
            'accent' => 'mint',
        ],
        'c' => [
            'key' => 'c',
            'title' => 'Cute',
            'subtitle' => '그림, 캐릭터, 귀여운 이미지 위주의 보드',
            'accent' => 'sky',
        ],
    ],
    'admin' => [
        'gate_key' => getenv('ADMIN_GATE_KEY') ?: 'momo-entry',
        'username' => getenv('ADMIN_USERNAME') ?: 'admin',
        'password' => getenv('ADMIN_PASSWORD') ?: 'admin',
    ],
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'pgsql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '5432',
        'database' => getenv('DB_NAME') ?: 'imageboard',
        'username' => getenv('DB_USER') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
];
