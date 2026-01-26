# Trail

Share links from Android with messages (280 chars max). Multi-user service with Google OAuth, admin interface, and public RSS feed.

**Stack**: PHP 8.4 + Slim 4 | MySQL 8.0 | Android (Kotlin + Compose + Ktor + Koin) | Docker

**Security**: OAuth, JWT, rate limiting (60/min), bot protection, CSRF tokens

## Quick Start

```bash
# 1. Configure
cp .env.example .env
cp config.yml.example config.yml
# Edit .env with your credentials

# 2. Install
cd scripts && uv run python install.py

# 3. Access
# Backend: http://localhost:8000
# Admin: http://localhost:8000/admin
# phpMyAdmin: http://localhost:8080
```

**Android**: Add `google-services.json`, update API URL in `di/KoinModules.kt`, run `./gradlew assembleDebug`

## API

### POST /api/auth/google
```json
{ "google_token": "..." }
→ { "jwt": "...", "user": {...} }
```

### POST /api/entries (authenticated)
```json
{ "url": "https://...", "message": "..." }
→ { "id": 123, "created_at": "..." }
```

### GET /api/entries?page=1&limit=20 (authenticated)
```json
→ { "entries": [...], "total": 100, "page": 1, "pages": 5 }
```

### GET /rss (public)
RSS 2.0 feed of all entries

### GET /rss/{user_id} (public)
Per-user RSS feed

## Deployment

```bash
cd scripts
uv run python full_deploy.py  # Migrations + FTP + health check
```

**Android**: `cd android && ./gradlew assembleRelease`

## Architecture Decisions

**Backend**: Slim 4 (PSR-7), PDO prepared statements, Gravatar MD5 pre-computation, database-backed rate limiting

**Android**: Ktor (coroutine-native), Koin (simpler than Hilt), Navigation 3 (type-safe), Kotlin Serialization (no reflection)

**Database**: Composite indexes `(user_id, created_at DESC)`, UNSIGNED INT, utf8mb4, InnoDB

**Performance**: RSS caching (5min TTL), pagination (20-50), OPcache, HTTP/2, LazyColumn

## Troubleshooting

**Config not found**: Copy `config.yml.example` to `config.yml`

**Env var not set**: Check `.env` exists and matches `config.yml` variables

**DB connection failed**: Verify MySQL running, check credentials, ensure DB/user exists

**429 Rate limit**: Wait for `Retry-After` header duration

**Google Sign-In fails**: Verify `google-services.json` and OAuth client ID in Google Cloud Console

**App crashes**: Check Crashlytics dashboard, verify API URL in `di/KoinModules.kt`

**Docker won't start**: Check port conflicts (8000, 3306, 8080), run `docker-compose logs`

**Permission denied**: Run `chmod +x scripts/*.py`

## Structure

```
backend/         # PHP 8.4 + Slim 4 (MVC, tests, Docker)
android/         # Kotlin + Compose (MVVM, Koin, tests)
migrations/      # SQL migrations
scripts/         # Python UV (install, migrate, deploy)
```

## Testing

**Backend**: `cd backend && composer test`

**Android**: `cd android && ./gradlew test`

**Integration**: Start Docker, run migrations, access http://localhost:8000/admin

---

**~7,100 lines** | Production-ready | MIT License
