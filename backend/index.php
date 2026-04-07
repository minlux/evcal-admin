<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$app = AppFactory::create();

// Detect base path from SCRIPT_NAME so the app works both at the server root
// (dev: SCRIPT_NAME=/index.php) and under a sub-path (prod: SCRIPT_NAME=/evcal-admin/api/index.php).
// We strip two segments (/api/index.php) to get the frontend base href (/evcal-admin).
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath   = rtrim(dirname(dirname($scriptName)), '/');
$app->setBasePath($basePath);

// Register routes
(require __DIR__ . '/src/routes/auth.php')($app);
(require __DIR__ . '/src/routes/events.php')($app);
(require __DIR__ . '/src/routes/public.php')($app);
(require __DIR__ . '/src/routes/test.php')($app);

// Middleware — order matters: last added = first executed (outermost).
// Error middleware must be outermost so it catches exceptions from all inner layers.

// CORS (outermost — runs first, adds headers to every response including errors)
$app->add(function (Request $request, $handler) use ($app): Response {
    if ($request->getMethod() === 'OPTIONS') {
        $response = $app->getResponseFactory()->createResponse();
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withStatus(200);
    }

    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

// Routing middleware
$app->addRoutingMiddleware();

// Error middleware (outermost — added last so it wraps everything)
$app->addErrorMiddleware(true, true, true);

$app->run();
