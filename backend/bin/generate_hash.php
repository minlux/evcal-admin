#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/generate_hash.php <password>\n");
    exit(1);
}

$password = $argv[1];
$hash     = password_hash($password, PASSWORD_BCRYPT);

echo $hash . PHP_EOL;
