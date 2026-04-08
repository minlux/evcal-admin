<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * Parse a relative offset string like +3d, -2m, +1y relative to $base (DateTime).
 * Supported units: d (day), m (month), y (year).
 * Returns a new DateTime or null if the string is not a valid offset.
 */
function parse_relative_date(string $value, \DateTime $base): ?\DateTime
{
    // Trim whitespace: query-string '+' is URL-decoded to ' ' by PHP
    $value = trim($value);

    // Sign is optional — unsigned values are treated as positive
    if (!preg_match('/^([+-]?\d+)([dmy])$/i', $value, $m)) {
        return null;
    }

    $amount = (int) $m[1];
    $unit   = strtolower($m[2]);

    $dt = clone $base;

    switch ($unit) {
        case 'd':
            $dt->modify("{$amount} days");
            break;
        case 'm':
            $dt->modify("{$amount} months");
            break;
        case 'y':
            $dt->modify("{$amount} years");
            break;
    }

    return $dt;
}

/**
 * Parse a date parameter: either a relative offset (+3d) or an ISO date (YYYY-MM-DD).
 * Falls back to $fallback when empty.
 */
function parse_date_param(?string $value, \DateTime $base, \DateTime $fallback): \DateTime
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $relative = parse_relative_date($value, $base);
    if ($relative !== null) {
        return $relative;
    }

    $dt = \DateTime::createFromFormat('Y-m-d', $value);
    return $dt !== false ? $dt : clone $fallback;
}

/**
 * Evaluate a tag filter expression against a set of tags.
 *
 * Grammar: comma = OR, plus = AND
 * "a,b,c+d"  → a || b || (c && d)
 *
 * Evaluation order: terms separated by commas are OR-ed;
 * within each comma-term, items separated by plus are AND-ed.
 */
function matches_tag_filter(string $filter, array $eventTags): bool
{
    $orTerms = explode(',', $filter);

    foreach ($orTerms as $orTerm) {
        $andParts = explode('+', $orTerm);
        $andMatch = true;

        foreach ($andParts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!in_array($part, $eventTags, true)) {
                $andMatch = false;
                break;
            }
        }

        if ($andMatch) {
            return true;
        }
    }

    return false;
}

return function (App $app): void {
    $app->get('/api/public/events', function (Request $request, Response $response): Response {
        $params = $request->getQueryParams();

        $today = new \DateTime('today');

        $hasStart = isset($params['start']) && $params['start'] !== '';
        $hasEnd   = isset($params['end'])   && $params['end']   !== '';

        // null means no boundary in that direction
        $startDate = $hasStart ? parse_date_param($params['start'], $today, $today) : null;
        $endDate   = $hasEnd   ? parse_date_param($params['end'],   $today, $today) : null;

        if ($startDate !== null) {
            $startDate->setTime(0, 0, 0);
        }
        if ($endDate !== null) {
            $endDate->setTime(23, 59, 59);
        }

        $tagFilter = isset($params['tags']) ? trim($params['tags']) : '';

        $db   = require __DIR__ . '/../db.php';
        $rows = $db->select('events', '*') ?: [];

        $results = [];

        foreach ($rows as $row) {
            $dates = $row['start'] !== '' ? explode(';', $row['start']) : [];
            $tags  = $row['tags']  !== '' ? explode(';', $row['tags'])  : [];

            // Tag filter
            if ($tagFilter !== '' && !matches_tag_filter($tagFilter, $tags)) {
                continue;
            }

            // Expand multi-date events: emit one entry per date that falls in range
            foreach ($dates as $idx => $dateStr) {
                $dateStr = trim($dateStr);
                if ($dateStr === '') {
                    continue;
                }

                $dt = \DateTime::createFromFormat('Y-m-d', $dateStr);
                if ($dt === false) {
                    // Try ISO 8601 datetime
                    $dt = new \DateTime($dateStr);
                }
                if ($dt === false) {
                    continue;
                }

                $dt->setTime(0, 0, 0);

                if ($startDate !== null && $dt < $startDate) {
                    continue;
                }
                if ($endDate !== null && $dt > $endDate) {
                    continue;
                }

                // followup = all dates in the event that come after this one
                $followup = [];
                foreach ($dates as $fIdx => $fDate) {
                    if ($fIdx <= $idx) {
                        continue;
                    }
                    $fDate = trim($fDate);
                    if ($fDate !== '') {
                        $followup[] = $fDate;
                    }
                }

                $results[] = [
                    'id'       => (int) $row['id'],
                    'title'    => $row['title'],
                    'start'    => $dt->format(\DateTime::ATOM),
                    'tags'     => $tags,
                    'description'     => $row['description'] ?? '',
                    'followup' => $followup,
                ];
            }
        }

        // Sort by start date ascending
        usort($results, fn($a, $b) => strcmp($a['start'], $b['start']));

        $response->getBody()->write(json_encode($results, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
