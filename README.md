# evcal-admin

A lightweight web application for managing calendar events. Built for small organizations that need a simple admin interface to create, edit, and delete events — and a public API endpoint for embedding event data into external websites.

The stack is intentionally minimal: a PHP backend (Slim 4) exposes a JWT-secured REST API, and an Angular 21 frontend provides the admin UI. Events support multiple dates, tags for filtering, and a description field.

---

## REST API

### Authentication

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/auth/login` | public | Login with username/password, returns a JWT |

### Events (protected)

All routes require `Authorization: Bearer <token>`.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/events` | List all events |
| GET | `/api/events/{id}` | Get a single event |
| POST | `/api/events` | Create an event |
| PUT | `/api/events/{id}` | Update an event |
| DELETE | `/api/events/{id}` | Delete an event |

### Public endpoint

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/public/events` | none | Expanded event list for embedding in external sites |

The public endpoint supports filtering by date range and tags:

- `?start=+0d&end=+30d` — events in the next 30 days (relative offsets: `+3d`, `-2m`, `+1y`, etc.)
- `?tags=feuerwehr,übung` — OR filter; use `+` for AND: `?tags=feuerwehr+übung`
- Without `start`/`end`, only today's events are returned

Multi-date events are expanded into one entry per date. Each entry includes a `followup` array with the remaining dates of that event.

---

## Getting running (development)

### Prerequisites

- PHP 8.1+ with Composer
- Node.js 18+ with npm
- A MySQL / MariaDB database
- Apache with `mod_rewrite` (or any server that can point to `backend/public/`)

### 1. Database

Create the database and table:

```sql
CREATE DATABASE evcal;

USE evcal;

CREATE TABLE events (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255),
  start TEXT,
  tags  TEXT,
  description  TEXT
);
```

### 2. Backend

```bash
cd backend
composer install
```

Edit `src/config.php` and set your database credentials and a strong JWT secret:

```php
define('JWT_SECRET', 'your-secret-here');
define('DB_HOST',    'localhost');
define('DB_NAME',    'evcal');
define('DB_USER',    'your-db-user');
define('DB_PASS',    'your-db-password');
```

Generate a bcrypt hash for your admin password and put it in `src/users.php`:

```bash
php bin/generate_hash.php yourpassword
```

Point your web server's document root to `backend/public/`. For local development with the built-in PHP server:

```bash
php -S localhost:8080 -t public
```

### 3. Frontend

```bash
cd frontend
npm install
ng serve
```

The dev server runs on `http://localhost:4200` and proxies all `/api` requests to `http://localhost:8080`.

---

## Production build

### Backend

No build step required — just deploy the `backend/` directory to your server (with `vendor/` included after `composer install`) and configure the document root to point to `backend/public/`.

### Frontend

The build bundles the Angular app **and** copies the PHP backend into a single `dist/` tree, so both can be deployed together.

Before building, make sure the backend dependencies are installed:

```bash
cd backend && composer install --no-dev --optimize-autoloader
```

Then build from the `frontend/` directory using npm (not `ng build` directly — the backend copy runs as a `postbuild` npm hook):

```bash
cd frontend
npm run build
```

The base href `/evcal-admin/` is already baked into the production configuration in `angular.json`. Fonts are bundled from npm packages — no internet connection required at runtime.

After the build, `frontend/dist/evcal-admin/browser/` contains everything needed for deployment:

```
frontend/dist/evcal-admin/browser/
├── index.html
├── main-*.js
├── styles-*.css
├── ...                 ← Angular app, served at /evcal-admin/
└── api/
    ├── index.php       ← PHP entry point, served at /evcal-admin/api/
    ├── .htaccess
    ├── src/
    ├── vendor/
    └── ...
```

Deploy the entire `browser/` folder to your server as the document root for `/evcal-admin/`.

Example Apache vhost snippet:

```apache
Alias /evcal-admin /var/www/evcal/browser

<Directory /var/www/evcal/browser>
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>

<Directory /var/www/evcal/browser/api/public>
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>
```

The `.htaccess` in the browser root handles Angular routing. The `.htaccess` in `api/public/` handles Slim's URL rewriting.
