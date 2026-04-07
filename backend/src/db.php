<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

use Medoo\Medoo;

return new Medoo([
    'type'     => 'mysql',
    'host'     => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASS,
    'charset'  => 'utf8mb4',
]);
