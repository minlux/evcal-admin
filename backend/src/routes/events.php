<?php

declare(strict_types=1);

use App\Middleware\JwtMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Convert a DB row to the API event shape.
 * start and tags are stored as semicolon-separated strings.
 */
function row_to_event(array $row): array
{
    return [
        'id'    => (int) $row['id'],
        'title' => $row['title'],
        'start' => $row['start'] !== '' ? explode(';', $row['start']) : [],
        'tags'  => $row['tags'] !== '' ? explode(';', $row['tags']) : [],
        'description'  => $row['description'] ?? '',
    ];
}

return function (App $app): void {
    $app->group('/api/events', function (RouteCollectorProxy $group): void {

        // GET /api/events — list all
        $group->get('', function (Request $request, Response $response): Response {
            $db     = require __DIR__ . '/../db.php';
            $rows   = $db->select('events', '*') ?: [];
            $events = array_map('row_to_event', $rows);

            $response->getBody()->write(json_encode($events, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // GET /api/events/{id} — single event
        $group->get('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
            $db  = require __DIR__ . '/../db.php';
            $row = $db->get('events', '*', ['id' => (int) $args['id']]);

            if (!$row) {
                $response->getBody()->write(json_encode(['error' => 'Event not found'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode(row_to_event($row), JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // POST /api/events — create
        $group->post('', function (Request $request, Response $response): Response {
            $db   = require __DIR__ . '/../db.php';
            $body = json_decode((string) $request->getBody(), true) ?? [];

            $title = trim((string) ($body['title'] ?? ''));
            if ($title === '') {
                $response->getBody()->write(json_encode(['error' => 'title is required'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $start = isset($body['start']) && is_array($body['start'])
                ? implode(';', array_map('trim', $body['start']))
                : (string) ($body['start'] ?? '');

            $tags = isset($body['tags']) && is_array($body['tags'])
                ? implode(';', array_map('trim', $body['tags']))
                : (string) ($body['tags'] ?? '');

            $db->insert('events', [
                'title' => $title,
                'start' => $start,
                'tags'  => $tags,
                'description'  => (string) ($body['description'] ?? ''),
            ]);

            $id  = (int) $db->id();
            $row = $db->get('events', '*', ['id' => $id]);

            $response->getBody()->write(json_encode(row_to_event($row), JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        });

        // PUT /api/events/{id} — update
        $group->put('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
            $db  = require __DIR__ . '/../db.php';
            $id  = (int) $args['id'];
            $row = $db->get('events', '*', ['id' => $id]);

            if (!$row) {
                $response->getBody()->write(json_encode(['error' => 'Event not found'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $body  = json_decode((string) $request->getBody(), true) ?? [];
            $title = trim((string) ($body['title'] ?? $row['title']));

            $start = isset($body['start']) && is_array($body['start'])
                ? implode(';', array_map('trim', $body['start']))
                : (isset($body['start']) ? (string) $body['start'] : $row['start']);

            $tags = isset($body['tags']) && is_array($body['tags'])
                ? implode(';', array_map('trim', $body['tags']))
                : (isset($body['tags']) ? (string) $body['tags'] : $row['tags']);

            $db->update('events', [
                'title' => $title,
                'start' => $start,
                'tags'  => $tags,
                'description'  => (string) ($body['description'] ?? $row['description']),
            ], ['id' => $id]);

            $updated = $db->get('events', '*', ['id' => $id]);

            $response->getBody()->write(json_encode(row_to_event($updated), JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // DELETE /api/events/{id}
        $group->delete('/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
            $db  = require __DIR__ . '/../db.php';
            $id  = (int) $args['id'];
            $row = $db->get('events', 'id', ['id' => $id]);

            if (!$row) {
                $response->getBody()->write(json_encode(['error' => 'Event not found'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $db->delete('events', ['id' => $id]);

            $response->getBody()->write(json_encode(['ok' => true], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

    })->add(new JwtMiddleware());
};
