# Trail

Multi-user link journaling service. Share URLs from Android with 280-char messages. Google OAuth, admin dashboard, public RSS.

**Stack**: PHP 8.4 + Apache + Slim 4 | MariaDB 10.11 | Android (Kotlin + Compose + Ktor + Koin)

**Security**: OAuth, JWT, rate limiting (60/min), bot protection, CSRF tokens, rootless containers

## Quick Start

```bash
# 1. Configure
cp .env.example .env
cp config.yml.example config.yml
cp config.yml.example backend/config.yml
# Edit .env and config files with credentials

# 2. Run
./run.sh

# Access:
# Backend:  http://localhost:18000
# Admin:    http://localhost:18000/admin/login.php
# Database: http://localhost:18080 (phpMyAdmin)
```

The script handles Docker, migrations, permissions, and displays all URLs.

## Deployment

**Backend** (build-then-deploy):

```bash
cd scripts
python3 verify_deployment_readiness.py  # Optional check
uv run python full_deploy.py            # Build + migrate + FTP + verify
```

**Process**: Builds `vendor/` locally (production has no Composer), runs migrations, uploads via FTP, verifies health.

**Android**: `cd android && ./gradlew assembleRelease`

## Architecture

**Backend**: Slim 4 (PSR-7), PDO prepared statements, Gravatar MD5 pre-computation, database rate limiting

**Android**: Ktor (coroutine-native), Koin (simpler than Hilt), Navigation 3 (type-safe), Kotlin Serialization

**Database**: Composite indexes `(user_id, created_at DESC)`, UNSIGNED INT, utf8mb4, InnoDB

**Performance**: RSS caching (5min), pagination (20-50), OPcache + JIT, Apache compression

**Deployment**: `vendor/` uploaded via FTP (production lacks Composer). Docker matches production (PHP 8.4 + Apache + MariaDB).

## API

```bash
# Android app auth
POST /api/auth/google
{ "google_token": "..." } → { "jwt": "...", "user": {...} }

# Create entry (authenticated)
POST /api/entries
{ "url": "https://...", "message": "..." } → { "id": 123, "created_at": "..." }

# List entries (authenticated)
GET /api/entries?page=1&limit=20
→ { "entries": [...], "total": 100, "page": 1, "pages": 5 }

# Public RSS
GET /rss              # All entries
GET /rss/{user_id}    # Per-user feed
```

## Web Admin

Access `/admin/` with Google OAuth. Features: view entries/users, dashboard stats, session auth (24h expiry).

## Structure

```
backend/         # PHP 8.4 + Slim 4 (MVC, tests, Docker)
android/         # Kotlin + Compose (MVVM, Koin, tests)
migrations/      # SQL migrations
scripts/         # Python UV (install, migrate, deploy)
```

## Production Notes

**Environment**: PHP 8.4 + Apache + MariaDB. FTP-only access, no Composer/SSH.

**Build workflow**: `composer install --no-dev` runs locally, generates `vendor/` (~10-15 MB), uploads to production.

**Why**: Production constraints require build-then-deploy. `vendor/` excluded from Git (cleaner repo), built fresh each deployment.

**Extensions**: Production has all required PHP extensions (mysqli, pdo_mysql, mbstring, gd, curl, openssl, json, zip, bcmath, intl, opcache). Docker matches exactly.

## Troubleshooting

**Config not found**: Copy `config.yml.example` to `config.yml`

**Env var not set**: Check `.env` exists and matches `config.yml` variables

**DB connection failed**: Verify MariaDB running, check credentials

**429 Rate limit**: Wait for `Retry-After` header duration

**Google Sign-In fails**: Verify `google-services.json` and OAuth client ID

**Docker won't start**: Check port conflicts (18000, 13306, 18080), run `docker compose logs`

**Vendor not found**: Run `cd scripts && uv run python prepare_deploy.py`

**Class not found on production**: Re-run `full_deploy.py` to rebuild and upload `vendor/`

## Testing

**Backend**: `cd backend && composer test`

**Android**: `cd android && ./gradlew test`

**Integration**: Start Docker, run migrations, access http://localhost:18000/admin

---

**~7,100 lines** | Production-ready | MIT License
