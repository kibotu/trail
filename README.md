# Trail

Link journaling service with Google OAuth, media uploads, and social engagement.

**Live:** https://trail.services.kibotu.net

## What It Does

- 140-character posts with automatic URL previews (via Iframely)
- Image attachments up to 20MB (WebP-optimized)
- Full-text search, claps, threaded comments, RSS feeds
- Real-time notifications, user muting, content reporting

## Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8.4+, Slim 4, MariaDB |
| Android | Jetpack Compose, Material 3, Ktor |
| Auth | Google OAuth 2.0, JWT, API Tokens |
| URL Enrichment | Iframely API (2000/mo limit, with fallback) |

## Quick Start

### Backend

```bash
cd backend
composer install
cp secrets.yml.example secrets.yml  # Edit with your credentials
php -S localhost:8000 -t public
```

### Android

```bash
cd android
./gradlew installDebug
```

Update `default_web_client_id` in `app/src/main/res/values/strings.xml` with your Google OAuth client ID.

### Deploy

```bash
./sync.sh  # Installs deps, uploads via FTP, runs migrations
```

## API

### Public (no auth)

```bash
curl https://trail.services.kibotu.net/api/entries
curl https://trail.services.kibotu.net/api/entries?q=search
curl https://trail.services.kibotu.net/api/users/nickname/entries
```

### Authenticated

Get your API token from your profile page after signing in.

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text": "Hello world!"}' \
  https://trail.services.kibotu.net/api/entries
```

**Full API docs:** https://trail.services.kibotu.net/api

## Authentication

Two methods, both use `Authorization: Bearer <token>`:

1. **API Tokens** — Persistent, never expire, managed from profile page
2. **Session JWT** — Auto-refreshed every 18 hours, 7-day expiry

## Twitter Import

Migrate your Twitter archive:

```bash
cd twitter
./migrate.sh --jwt YOUR_TOKEN --archive twitter-backup.zip
```

~5 minutes for 2,788 tweets. Preserves timestamps, images, and favorites→claps.

## Project Structure

```
trail/
├── backend/           # PHP API
│   ├── public/        # Web root
│   ├── src/           # Controllers, Models, Services, Middleware
│   └── secrets.yml    # Configuration (not in git)
├── android/           # Jetpack Compose app
├── twitter/           # Archive importer
├── migrations/        # SQL migrations (auto-run by sync.sh)
└── sync.sh            # Deployment script
```

## Configuration

Edit `backend/secrets.yml`:

```yaml
database:
  host: localhost
  name: trail_db
  user: trail_user
  password: YOUR_PASSWORD

google_oauth:
  client_id: YOUR_CLIENT_ID
  client_secret: YOUR_CLIENT_SECRET

jwt:
  secret: YOUR_256_BIT_SECRET
  expiry_hours: 168

security:
  rate_limit:
    enabled: true
    requests_per_minute: 180
```

## Security

- HTTPS enforced, secure cookies
- Rate limiting: 180 req/min, 3000 req/hr
- XSS prevention, SQL injection protection (prepared statements)
- CSRF protection, CORS middleware

## License

Apache 2.0
