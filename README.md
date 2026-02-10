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

---

## Backend Installation & Deployment

### Prerequisites

- [ ] **PHP 8.4+** with extensions: `pdo_mysql`, `json`, `mbstring`, `curl`, `gd`
- [ ] **Composer** (https://getcomposer.org/)
- [ ] **MariaDB/MySQL** database server
- [ ] **lftp** for FTP deployment (`brew install lftp` on macOS, `apt install lftp` on Linux)
- [ ] **Web hosting** with FTP access and PHP support
- [ ] **Google Cloud Console** project for OAuth credentials
- [ ] **Iframely API key** (optional, for URL previews - https://iframely.com/)

### Step 1: Clone and Install Dependencies

```bash
git clone https://github.com/kibotu/trail.git
cd trail/backend
composer install
```

### Step 2: Configure Database

Create a MySQL/MariaDB database:

```sql
CREATE DATABASE trail_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'trail_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON trail_db.* TO 'trail_user'@'localhost';
FLUSH PRIVILEGES;
```

### Step 3: Set Up Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the **Google+ API** and **Google Identity Services**
4. Go to **APIs & Services → Credentials**
5. Create **OAuth 2.0 Client ID** (Web application)
6. Add authorized redirect URI: `https://your-domain.com/admin/callback.php`
7. Copy the **Client ID** and **Client Secret**

### Step 4: Configure secrets.yml

```bash
cp secrets.yml.example secrets.yml
```

Edit `secrets.yml` with your values:

```yaml
# Application Settings
app:
  environment: production
  base_url: https://your-domain.com
  rss_title: "Trail - Link Journal"
  rss_description: "Public link journal feed"
  nickname_salt: CHANGE_TO_RANDOM_STRING_32_CHARS
  entry_hash_salt: CHANGE_TO_ANOTHER_RANDOM_STRING
  max_text_length: 140
  max_images_per_entry: 3

# Database Configuration
database:
  host: localhost
  port: 3306
  name: trail_db
  user: trail_user
  password: "your_database_password"
  prefix: trail_
  charset: utf8mb4
  collation: utf8mb4_unicode_ci

# FTP Configuration (for deployment)
ftp:
  host: ftp.your-host.com
  port: 21
  user: ftp_username
  password: your_ftp_password
  remote_path: /

# Google OAuth
google_oauth:
  client_id: YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com
  client_secret: GOCSPX-YOUR_CLIENT_SECRET
  redirect_uri: https://your-domain.com/admin/callback.php

# JWT Configuration
jwt:
  secret: your_256_bit_secret_key_at_least_32_chars
  expiry_hours: 168  # 7 days

# Security Settings
security:
  rate_limit:
    enabled: true
    requests_per_minute: 180
    requests_per_hour: 3000
  bot_protection:
    enabled: true
    require_user_agent: true
    block_suspicious_patterns: true

# Iframely API (optional, for URL previews)
iframely:
  api_key: your_iframely_api_key
  api_url: https://iframe.ly/api/iframely

# Development Settings (only used when environment=development)
development:
  dev_users:
    - email: dev@example.com
      name: Dev User
      is_admin: true

production:
  admin_email: admin@example.com
```

### Step 5: Run Local Development Server

```bash
php -S localhost:8000 -t public
```

Visit http://localhost:8000 to verify the installation.

### Step 6: Deploy to Production

The `sync.sh` script handles everything:

```bash
./sync.sh
```

**What sync.sh does:**
1. Verifies security configuration
2. Installs production dependencies (`composer install --no-dev`)
3. Uploads files via FTP (excludes dev files, tests, uploads)
4. Runs database migrations automatically
5. Cleans up temporary migration files

### Step 7: Verify Deployment

- [ ] Visit `https://your-domain.com` - Landing page loads
- [ ] Visit `https://your-domain.com/admin` - OAuth login works
- [ ] Visit `https://your-domain.com/api/entries` - API returns JSON
- [ ] Visit `https://your-domain.com/rss` - RSS feed works

---

## Android App

```bash
cd android
./gradlew installDebug
```

Update `default_web_client_id` in `app/src/main/res/values/strings.xml` with your Google OAuth client ID.

---

## Twitter Archive Import

Migrate your Twitter/X archive to Trail.

### Prerequisites

- [ ] **Python 3.7+** installed
- [ ] **Twitter archive ZIP** from Twitter settings
- [ ] **Trail API key** from your profile page

### Step 1: Request Twitter Archive

1. Go to [Twitter Settings](https://twitter.com/settings/download_your_data)
2. Click **Request archive**
3. Wait 24-48 hours for email notification
4. Download the ZIP file (do NOT extract it)

### Step 2: Get Your Trail API Key

1. Sign in to Trail at https://trail.services.kibotu.net
2. Go to your **Profile** page
3. Generate an **API Token** (never expires)
4. Copy the token

### Step 3: Run Migration

```bash
cd twitter

# Option A: Using environment variable (recommended)
export TRAIL_API_KEY="your_api_key_here"
./migrate.sh --archive path/to/twitter-backup.zip

# Option B: Inline API key
./migrate.sh --api-key YOUR_API_KEY --archive path/to/twitter-backup.zip
```

The script will:
1. Install `uv` package manager (if not present)
2. Extract the ZIP archive
3. Parse `data/tweets.js`
4. Import tweets with original timestamps
5. Upload images (up to 3 per tweet)
6. Cache progress for resume capability

### Migration Options

```bash
# Test run (no actual import)
./migrate.sh --api-key KEY --archive backup.zip --dry-run --limit 10

# Verbose mode (shows curl equivalents)
./migrate.sh --api-key KEY --archive backup.zip -v

# Custom rate limit (default: 100ms between requests)
./migrate.sh --api-key KEY --archive backup.zip --delay 500

# Resume interrupted migration (automatic)
./migrate.sh --api-key KEY --archive backup.zip
```

### What Gets Migrated

| Data | Migrated | Notes |
|------|----------|-------|
| Tweet text | Yes | Up to 280 characters |
| Original timestamps | Yes | Preserves exact date/time |
| Images (JPG/PNG/GIF/WebP) | Yes | Up to 3 per tweet, <20MB each |
| Favorites/Likes count | Yes | Converted to claps (capped at 50) |
| Retweets | Yes | Imported as regular entries with "RT @" prefix |
| Videos (MP4/MOV/WebM) | No | Skipped automatically |
| Reply threading | No | Imported as standalone entries |
| Retweet counts | No | Not available in archive |
| View/impression counts | No | Not included in Twitter archives |
| Quote tweets | Partial | Text only, no embedded tweet |
| Polls | No | Not supported |
| Bookmarks | No | Not included in archive |

### Performance

- ~2,800 tweets in 5-10 minutes
- Uses connection pooling and automatic retries
- 100ms delay between requests (configurable)
- Progress cached in `.migration_cache/`

### Troubleshooting

| Issue | Solution |
|-------|----------|
| "Archive file not found" | Check path: `ls -la *.zip` |
| "Invalid archive structure" | Use official Twitter archive ZIP |
| "401 Unauthorized" | Get new API key from trail.services.kibotu.net |
| "429 Too Many Requests" | Add `--delay 500` for slower rate |
| Import failures | Images >20MB skipped, script continues |
| Start fresh | Delete cache: `rm -rf .migration_cache/` |

---

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

---

## Authentication

Two methods, both use `Authorization: Bearer <token>`:

1. **API Tokens** — Persistent, never expire, managed from profile page
2. **Session JWT** — Auto-refreshed every 18 hours, 7-day expiry

---

## Project Structure

```
trail/
├── backend/           # PHP API
│   ├── public/        # Web root (index.php, admin/, assets/)
│   ├── src/           # Controllers, Models, Services, Middleware
│   ├── templates/     # HTML templates
│   ├── tests/         # PHPUnit tests
│   └── secrets.yml    # Configuration (not in git)
├── android/           # Jetpack Compose app
├── twitter/           # Archive importer
│   ├── migrate.sh     # Main migration script
│   ├── import_twitter_archive.py
│   └── README.md
├── migrations/        # SQL migrations (auto-run by sync.sh)
├── sync.sh            # Deployment script
└── test-all.sh        # Run all tests
```

---

## Configuration Reference

Full `backend/secrets.yml` example:

```yaml
app:
  environment: development  # or "production"
  base_url: https://your-domain.com
  rss_title: "Trail - Link Journal"
  rss_description: "Public link journal feed"
  nickname_salt: random_string_for_nickname_hashing
  entry_hash_salt: random_string_for_entry_id_hashing
  max_text_length: 140
  max_images_per_entry: 3

database:
  host: localhost
  port: 3306
  name: trail_db
  user: trail_user
  password: "your_password"
  prefix: trail_
  charset: utf8mb4
  collation: utf8mb4_unicode_ci

ftp:
  host: ftp.your-host.com
  port: 21
  user: ftp_user
  password: ftp_password
  remote_path: /

google_oauth:
  client_id: YOUR_CLIENT_ID.apps.googleusercontent.com
  client_secret: YOUR_CLIENT_SECRET
  redirect_uri: https://your-domain.com/admin/callback.php

jwt:
  secret: 32_character_or_longer_secret_key
  expiry_hours: 168

security:
  rate_limit:
    enabled: true
    requests_per_minute: 180
    requests_per_hour: 3000
  bot_protection:
    enabled: true
    require_user_agent: true
    block_suspicious_patterns: true

iframely:
  api_key: your_iframely_api_key
  api_url: https://iframe.ly/api/iframely

development:
  dev_users:
    - email: dev@example.com
      name: Dev User
      is_admin: true

production:
  admin_email: admin@example.com
```

---

## Security

- HTTPS enforced, secure cookies
- Rate limiting: 180 req/min, 3000 req/hr
- XSS prevention, SQL injection protection (prepared statements)
- CSRF protection, CORS middleware
- Bot protection with suspicious pattern detection

---

## License

Apache 2.0
