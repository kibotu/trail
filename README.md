# Trail

Multi-user link journaling service. Share text posts (max 280 characters) with URLs and emojis from Android. Google OAuth, admin dashboard, public RSS.

**Stack**: PHP 8.4 + Apache + Slim 4 | MariaDB 10.11 | Android (Kotlin + Compose + Ktor + Koin)

**Security**: OAuth, JWT, rate limiting (60/min), XSS protection, bot protection, CSRF tokens, rootless containers

## Quick Start

```bash
# Configure
cp .env.example .env
cp config.yml.example config.yml
cp config.yml.example backend/config.yml
# Edit .env and config files with credentials

# Run
./run.sh

# Access:
# Backend:  http://localhost:18000
# Admin:    http://localhost:18000/admin/login.php
# Database: http://localhost:18080 (phpMyAdmin)
```

### Development Mode (Skip OAuth)

For local development without Google OAuth:

```bash
# Set development mode in .env
APP_ENV=development

# Start services
cd backend && make up

# Login at http://localhost:18000/admin/login.php
# Click any dev user (no OAuth required)
```

**Dev users**: `dev@example.com`, `admin@example.com`, `user@example.com` (configured in `config.yml`)

## API

```bash
# Android app auth
POST /api/auth/google
{ "google_token": "..." } → { "jwt": "...", "user": {...} }

# Create entry (authenticated) - Max 280 characters
POST /api/entries
{ "text": "Check this out! https://example.com 🎉" } → { "id": 123, "created_at": "..." }
# Text is sanitized to prevent XSS while preserving URLs and emojis

# List entries (authenticated)
GET /api/entries?page=1&limit=20
→ { "entries": [...], "total": 100, "page": 1, "pages": 5 }

# Public RSS
GET /rss              # All entries
GET /rss/{user_id}    # Per-user feed
```

**Text field**: Single field supporting URLs (http://, https://, www.), emojis, UTF-8. XSS protection blocks scripts, event handlers, dangerous protocols while preserving legitimate content.

## Testing

```bash
# Quick test (recommended)
./run.sh
./test-api.sh        # API security + validation tests
cd backend && composer test  # Unit tests

# What gets tested:
# ✅ All API endpoints (public, authenticated, admin)
# ✅ XSS prevention (5 attack vectors)
# ✅ Input validation (empty, too long, invalid UTF-8)
# ✅ Authentication & authorization
# ✅ Rate limiting (60 requests/min)
# ✅ Content preservation (URLs, emojis)
```

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

## Security

**XSS Prevention**: All script tags, JavaScript protocols, and event handlers stripped. URLs and emojis preserved.

**What's blocked**: `<script>`, `javascript:`, `vbscript:`, `data:` protocols, event handlers (`onclick`, `onerror`), `<iframe>`, `<object>`, `<embed>`, PHP code

**What's preserved**: URLs (http://, https://, www.), emojis, UTF-8 characters, plain text

**Validation**: UTF-8 encoding check, 280-character limit, dangerous pattern detection, HTML sanitization

## Production Notes

**Environment**: PHP 8.4 + Apache + MariaDB. FTP-only access, no Composer/SSH.

**Build workflow**: `composer install --no-dev` runs locally, generates `vendor/` (~10-15 MB), uploads to production.

**Why**: Production constraints require build-then-deploy. `vendor/` excluded from Git (cleaner repo), built fresh each deployment.

**Extensions**: Production has all required PHP extensions (mysqli, pdo_mysql, mbstring, gd, curl, openssl, json, zip, bcmath, intl, opcache). Docker matches exactly.

## Structure

```
backend/         # PHP 8.4 + Slim 4 (MVC, tests, Docker)
android/         # Kotlin + Compose (MVVM, Koin, tests)
migrations/      # SQL migrations
scripts/         # Python UV (install, migrate, deploy)
```

## Environment Switching

**Development** (skip OAuth):
```bash
# In .env
APP_ENV=development

# Restart
cd backend && make restart
```

**Production** (require OAuth):
```bash
# In .env
APP_ENV=production

# Restart
cd backend && make restart
```

## Troubleshooting

**Config not found**: Copy `config.yml.example` to `config.yml`

**Env var not set**: Check `.env` exists and matches `config.yml` variables

**DB connection failed**: Verify MariaDB running, check credentials

**429 Rate limit**: Wait for `Retry-After` header duration

**Google Sign-In fails**: Verify `google-services.json` and OAuth client ID

**Docker won't start**: Check port conflicts (18000, 13306, 18080), run `docker compose logs`

**Vendor not found**: Run `cd scripts && uv run python prepare_deploy.py`

**Class not found on production**: Re-run `full_deploy.py` to rebuild and upload `vendor/`

**Dev login not showing**: Set `APP_ENV=development` in `.env`, restart: `cd backend && make restart`

---

**~7,100 lines** | Production-ready | MIT License
