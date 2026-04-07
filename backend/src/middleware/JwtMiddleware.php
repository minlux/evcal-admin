<?php

declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class JwtMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('Missing or malformed Authorization header');
        }

        $token = substr($authHeader, 7);

        try {
            require_once __DIR__ . '/../config.php';
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        } catch (\Throwable $e) {
            return $this->unauthorized('Invalid or expired token: ' . $e->getMessage());
        }

        // Validate password hash CRC so tokens are invalidated on password change
        $users = require __DIR__ . '/../users.php';
        $user  = null;
        foreach ($users as $u) {
            if ($u['id'] === (int) $decoded->uid) {
                $user = $u;
                break;
            }
        }

        if ($user === null) {
            return $this->unauthorized('User not found');
        }

        if (!isset($decoded->phc) || $decoded->phc !== crc32($user['password_hash'])) {
            return $this->unauthorized('Token invalidated due to password change');
        }

        $request = $request->withAttribute('jwt', $decoded);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => $message], JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
