<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {
    $app->get('/api/test', function (Request $request, Response $response): Response {
        require_once __DIR__ . '/../config.php';

        // Test DB connection
        $dbStatus  = 'ok';
        $dbError   = null;
        try {
            $db = require __DIR__ . '/../db.php';
            $db->query('SELECT 1');
        } catch (\Throwable $e) {
            $dbStatus = 'error';
            $dbError  = $e->getMessage();
        }

        $info = [
            'status'       => 'ok',
            'php_version'  => PHP_VERSION,
            'php_sapi'     => PHP_SAPI,
            'extensions'   => [
                'pdo'       => extension_loaded('pdo'),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'openssl'   => extension_loaded('openssl'),
                'mbstring'  => extension_loaded('mbstring'),
                'json'      => extension_loaded('json'),
            ],
            'database'     => [
                'status' => $dbStatus,
                'error'  => $dbError,
            ],
            'server'       => [
                'software'    => $_SERVER['SERVER_SOFTWARE'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI']     ?? null,
                'script_name' => $_SERVER['SCRIPT_NAME']     ?? null,
            ],
        ];

        $response->getBody()->write(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
