# evcal-admin — Project Specification

## Overview

A web application to manage calendar entries, built with:
- **Frontend**: Angular + FullCalendar + Angular Material
- **Backend**: PHP (Slim 4 + Medoo + firebase/php-jwt)
- **Database**: MySQL / MariaDB

---

## Project Structure

```
evcal-admin/
├── frontend/          # Angular app
├── backend/           # PHP REST API
└── spec.md            # This file
```

---

## 1. Database

### Table: `events`

| Column  | Type | Notes |
|---------|------|-------|
| `id`    | INT AUTO_INCREMENT PRIMARY KEY | |
| `title` | VARCHAR(255) | Event title, e.g. `"Übung Feuerwehr"` |
| `start` | TEXT | Semicolon-separated ISO 8601 timestamps, e.g. `"2026-06-10T19:00:00;2026-06-13T19:00:00"` |
| `tags`  | TEXT | Semicolon-separated keywords, e.g. `"feuerwehr;übung"` |
| `description`  | TEXT | Will later hold Markdown text |

> **Note on `start` separator:** Colons appear inside ISO timestamps (`HH:MM:SS`), so semicolon is used as the list separator to avoid ambiguity.

No users table — users are hardcoded in PHP.

---

## 2. Backend (PHP)

### Dependencies (Composer)

- `slim/slim` + `slim/psr7` — HTTP micro-framework and routing
- `catfan/medoo` — lightweight database query builder (PDO wrapper)
- `firebase/php-jwt` — JWT creation and validation

### Directory Structure

```
backend/
├── composer.json
├── index.php              # Slim app entry point
├── .htaccess              # URL rewriting for Apache
├── src/
│   ├── config.php         # JWT secret, DB credentials
│   ├── db.php             # Medoo instance
│   ├── users.php          # Hardcoded users with bcrypt password hashes
│   ├── middleware/
│   │   └── JwtMiddleware.php  # Validates Bearer token on protected routes
│   └── routes/
│       ├── auth.php       # POST /api/auth/login
│       ├── events.php     # CRUD routes for events (JWT-protected)
│       └── public.php     # GET /api/public/events (no auth, CORS)
└── bin/
    └── generate_hash.php  # CLI utility to generate bcrypt hashes
```

### API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/auth/login` | public | Login, returns JWT |
| GET | `/api/events` | JWT | List all events |
| GET | `/api/events/{id}` | JWT | Get single event |
| POST | `/api/events` | JWT | Create event |
| PUT | `/api/events/{id}` | JWT | Update event |
| DELETE | `/api/events/{id}` | JWT | Delete event |
| GET | `/api/public/events` | public (CORS) | Flat event list for external websites |

#### Public endpoint: `GET /api/public/events`

Returns a flat list of events, with multi-date events expanded into one entry per date. CORS-enabled, no authorization required.

**Query parameters:**

| Parameter | Format | Default | Description |
|-----------|--------|---------|-------------|
| `tags` | see below | — | Filter by tags |
| `start` | relative offset | today | Lower bound date |
| `end` | relative offset | today | Upper bound date |

If neither `start` nor `end` is given, only today's events are returned.

**`tags` filter syntax:**

- Values separated by `,` are OR-combined
- Values joined with `+` are AND-combined
- Example: `?tags=a,b,c+d` → `a || b || (c && d)`

**Relative date offset format for `start` / `end`:**

- `+3d` / `-3d` — days relative to today
- `+2m` / `-2m` — months relative to today
- `+1y` / `-1y` — years relative to today

**Response item shape:**

```json
{
  "id": 1,
  "title": "Übung Feuerwehr",
  "start": "2026-06-10T19:00:00",
  "tags": ["feuerwehr", "übung"],
  "description": "...",
  "followup": ["2026-06-13T19:00:00"]
}
```

`followup` lists all remaining dates of this event (i.e. all `start` entries after the current one).

### Auth Flow

1. Client POSTs `{ "username": "...", "password": "..." }` to `/api/auth/login`
2. Backend checks credentials against hardcoded users in `src/users.php`
3. On success, returns a signed JWT containing:
   - standard claims (`sub`, `iat`, `exp`)
   - `uid`: the user's integer `id`
   - `phc`: CRC32 of the user's bcrypt password hash (used for invalidation)
4. All protected routes are guarded by `JwtMiddleware` which:
   - Validates the `Authorization: Bearer <token>` header
   - Re-computes CRC32 of the current password hash and compares it to `phc` in the token — mismatch means the password was changed, token is rejected
5. Returns HTTP 401 on missing, invalid, or invalidated token

### Users

Defined in `src/users.php` as a static PHP array with pre-computed bcrypt hashes:

```php
return [
    ['id' => 1, 'username' => 'admin', 'password_hash' => '$2y$10$...'],
];
```

Use `php bin/generate_hash.php <password>` to generate hashes.

---

## 3. Frontend (Angular)

### General

- Standalone components throughout — no `NgModule`s
- Angular 21 (latest)
- ESLint (`@angular-eslint`) + Prettier for code quality and formatting

### Dependencies (npm)

- `@angular/material` + `@angular/cdk` (MatTable, MatDialog, MatChipsModule, MatFormField, DatePicker, etc.)

### Directory Structure

```
frontend/
├── proxy.conf.json        # Dev proxy: /api/* → local PHP backend
├── src/app/
│   ├── app.routes.ts
│   ├── core/
│   │   ├── services/
│   │   │   ├── auth.service.ts     # Login, JWT storage, isLoggedIn()
│   │   │   └── event.service.ts    # CRUD calls to /api/events
│   │   ├── guards/
│   │   │   └── auth.guard.ts       # Redirects to /login if not authenticated
│   │   └── interceptors/
│   │       └── jwt.interceptor.ts  # Attaches Authorization header to all requests
│   └── features/
│       ├── login/
│       │   └── login.component.ts  # Username/password form
│       ├── event-list/
│       │   └── event-list.component.ts  # Table/list of events
│       └── event-form/
│           └── event-form.component.ts  # Create/edit dialog (tags as Material chips)
```

### Routing

| Path | Component | Guard |
|------|-----------|-------|
| `/login` | `LoginComponent` | — |
| `/` | `EventListComponent` | `AuthGuard` |

### Data Model (Angular-side)

```typescript
interface CalendarEvent {
  id: number;
  title: string;
  start: string[];   // array of ISO 8601 timestamps; serialized as semicolon-separated in DB
  tags: string[];    // array of tag strings; serialized as comma-separated in DB
  description: string;      // raw string for now; later rendered as Markdown
}
```

### Dev Proxy

`proxy.conf.json` forwards all `/api/*` requests to the local PHP backend, avoiding CORS issues during development.

---

## 4. Data Format Notes

- **`start`** field: stored in the DB as a semicolon-separated string of ISO 8601 timestamps. The backend splits/joins on `;` when reading/writing. The frontend works with a `string[]`.
- **`tags`** field: stored in the DB as a semicolon-separated string. The backend splits/joins on `;` when reading/writing. The frontend works with a `string[]`.
- **`description`** field: intended to eventually hold **Markdown** text. For now stored and edited as a plain string.

---

## Out of Scope (for now)

- Token refresh / sliding expiry
- Role-based access control
- Markdown rendering in the UI
- Docker / containerization
- Production build pipeline
