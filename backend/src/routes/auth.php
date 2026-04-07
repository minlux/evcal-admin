<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {
    $app->post('/api/auth/login', function (Request $request, Response $response): Response {
        require_once __DIR__ . '/../config.php';

        $body = json_decode((string) $request->getBody(), true);

        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            $response->getBody()->write(json_encode(['error' => 'username and password required'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $users = require __DIR__ . '/../users.php';

        $user = null;
        foreach ($users as $u) {
            if ($u['username'] === $username) {
                $user = $u;
                break;
            }
        }

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $now     = time();
        $payload = [
            'sub' => $user['username'],
            'uid' => $user['id'],
            'iat' => $now,
            'exp' => $now + 8 * 3600,
            'phc' => crc32($user['password_hash']),
        ];

        $token = JWT::encode($payload, JWT_SECRET, 'HS256');

        $response->getBody()->write(json_encode(['token' => $token], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });
};
