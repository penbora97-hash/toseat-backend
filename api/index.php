<?php

// បង្កើត Temporary Storage Folders នៅលើ Vercel
$storagePaths = [
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
    '/tmp/bootstrap/cache',
];

foreach ($storagePaths as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// កំណត់ Storage Path និង View Compiled Path ទៅកាន់ /tmp
putenv('APP_STORAGE=/tmp/storage');
putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');

require __DIR__ . '/../public/index.php';
