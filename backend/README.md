# Trail Backend

PHP backend for Trail. Handles auth, entries, media, search, and notifications.

## Features

- Google OAuth 2.0 + JWT + persistent API tokens
- Entry CRUD with 280-character limit, URL preview enrichment (Iframely + embed fallback)
- Automatic short URL resolution (t.co, bit.ly, etc.) to final destinations
- Chunked media upload (20MB max per file, up to 3 per entry)
- Video support: MP4, WebM, MOV with custom player controls
- Full-text search with relevance ranking
- Claps, threaded comments, @mention notifications
- View tracking with 24-hour deduplication
- User muting, content reporting
- RSS feeds (global + per-user)
- Rate limiting, bot protection, XSS/CSRF/SQLi hardening
- Custom profile images, header backgrounds, bios
- Admin dashboard with link health checking and short URL migration tools

## Tech Stack

- PHP 8.4+ with strict types
- Slim Framework 4 (PSR-7)
- MySQL/MariaDB
- JWT (firebase/php-jwt)
- Iframely API (URL previews)
- embed/embed (fallback URL metadata)

## Prerequisites

- PHP 8.4+ with extensions: `pdo_mysql`, `json`, `mbstring`, `curl`, `gd`
- ffprobe (optional, for video dimension detection)
- Composer (https://getcomposer.org/)
- MariaDB/MySQL database server
- lftp for FTP deployment (`brew install lftp` on macOS, `apt install lftp` on Linux)
- Web hosting with FTP access and PHP support
- Google Cloud Console project for OAuth credentials
- Iframely API key (optional, for URL previews - https://iframely.com/)

## Setup

### 1. Install Dependencies

```bash
cd backend
composer install
```

### 2. Create Database

```sql
CREATE DATABASE trail_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'trail_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON trail_db.* TO 'trail_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configure Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the **Google+ API** and **Google Identity Services**
4. Go to **APIs & Services → Credentials**
5. Create **OAuth 2.0 Client ID** (Web application)
6. Add authorized redirect URI: `https://your-domain.com/admin/callback.php`
7. Copy the **Client ID** and **Client Secret**

### 4. Configure secrets.yml

```bash
cp secrets.yml.example secrets.yml
```

Edit `secrets.yml` with your values. Key sections:

```yaml
app:
  environment: production
  base_url: https://your-domain.com
  nickname_salt: CHANGE_TO_RANDOM_STRING_32_CHARS
  entry_hash_salt: CHANGE_TO_ANOTHER_RANDOM_STRING

database:
  host: localhost
  name: trail_db
  user: trail_user
  password: "your_database_password"

ftp:
  host: ftp.your-host.com
  user: ftp_username
  password: your_ftp_password
  remote_path: /

google_oauth:
  client_id: YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com
  client_secret: GOCSPX-YOUR_CLIENT_SECRET
  redirect_uri: https://your-domain.com/admin/callback.php

jwt:
  secret: your_256_bit_secret_key_at_least_32_chars
  expiry_hours: 168  # 7 days

iframely:
  api_key: your_iframely_api_key
  api_url: https://iframe.ly/api/iframely
```

See `secrets.yml.example` for all available options.

### 5. Run Local Development Server

```bash
php -S localhost:8000 -t public
```

Visit http://localhost:8000 to verify the installation.

## Deployment

Deploy to production using the `sync.sh` script from the project root:

```bash
cd ..
./sync.sh
```

**What sync.sh does:**

1. Verifies security configuration
2. Installs production dependencies (`composer install --no-dev`)
3. Uploads files via FTP (excludes dev files, tests, uploads)
4. Runs database migrations automatically
5. Cleans up temporary migration files

**Verify deployment:**

- Visit `https://your-domain.com` - Landing page loads
- Visit `https://your-domain.com/admin` - OAuth login works
- Visit `https://your-domain.com/api/entries` - API returns JSON
- Visit `https://your-domain.com/rss` - RSS feed works

## Testing

```bash
# Run all tests
composer test

# Or directly with PHPUnit
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Integration/
```

## Project Structure

```
backend/
├── public/              # Web root
│   ├── index.php       # API entry point
│   ├── admin/          # Admin interface
│   ├── assets/         # CSS, JS, fonts, images
│   └── .htaccess       # URL rewriting
├── src/
│   ├── Config/         # Configuration loader
│   ├── Controllers/    # Request handlers (15 controllers)
│   ├── Database/       # Database connection
│   ├── Middleware/     # Auth, CORS, CSRF, rate limiting
│   ├── Models/         # Data models (12 models)
│   └── Services/       # Business logic (16 services)
├── templates/          # HTML templates
├── tests/              # PHPUnit tests
│   ├── Unit/
│   └── Integration/
├── vendor/             # Composer dependencies (3.2MB)
├── composer.json       # Dependencies
├── phpunit.xml         # Test configuration
└── secrets.yml         # Configuration (not in git)
```

## Configuration

All configuration is in `secrets.yml`. Key settings:

- **app.environment**: `development` or `production`
- **app.max_text_length**: Character limit (default: 140)
- **app.max_images_per_entry**: Image limit per entry (default: 3)
- **security.rate_limit**: API rate limiting (180/min, 3000/hr)
- **jwt.expiry_hours**: JWT token expiration (default: 168 = 7 days)

See `secrets.yml.example` for complete reference.

## API Documentation

Full REST API with public and authenticated endpoints.

**Live documentation:** https://trail.services.kibotu.net/api

Generate an API token from your profile page after signing in.

## License

Apache 2.0
