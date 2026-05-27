# Evasion ERP

## Cursor Cloud specific instructions

### Overview

This is a custom PHP 8 MVC ERP application (no framework) for multi-showroom retail and WooCommerce operations. The `main` branch is empty; all code lives on feature branches. The branch `cursor/modern-erp-platform-0f75` contains the cleaner MVC architecture and is recommended for development.

### Required Services

| Service | How to start |
|---------|-------------|
| **MySQL 8** | `sudo mysqld --user=mysql &` (data dir: `/var/lib/mysql`) |
| **PHP dev server** | `composer serve` (runs `php -S 0.0.0.0:8080 -t public`) |

### Database Setup

After MySQL is running, import the schema and seed data if the database does not yet exist:

```bash
mysql -u root -prootpass -h 127.0.0.1 < database/schema.sql
mysql -u root -prootpass -h 127.0.0.1 < database/seed.sql
```

The `.env` file must exist (copy from `.env.example`) with `DB_PASSWORD=rootpass`.

### Lint / Test / Build

- **Lint all PHP files:** `find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;`
- **Lint (composer script):** `composer lint`
- **No automated test suite** exists in this repo.
- **No build step** — raw PHP + vanilla JS with CDN-loaded libraries (TailwindCSS, Alpine.js, Chart.js).

### Dev Server

- Start: `composer serve` (port 8080)
- Health check: `curl http://localhost:8080/api/health`
- Frontend SPA loads at `/` without login; JWT auth is API-only via `/api/auth/login`.

### First-time Setup

Register the initial admin via the API (works only when users table is empty):

```bash
curl -X POST http://localhost:8080/api/auth/register-admin \
  -H "Content-Type: application/json" \
  -d '{"name":"Admin Dev","email":"admin@evasion.dev","password":"devpassword123"}'
```

### Gotchas

- MySQL in this environment requires `sudo mysqld --user=mysql &` to start manually (systemd service scripts don't work in the container).
- Connect to MySQL via TCP (`-h 127.0.0.1`) rather than socket to avoid permission issues.
- The `composer.json` has no external dependencies — `composer install` only generates the PSR-4 autoloader.
- The frontend SPA does not have a login page route; authentication is handled via API JWT tokens stored in localStorage.
