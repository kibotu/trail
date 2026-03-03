<p align="center">
  <img src="../android/app/src/main/ic_launcher-playstore.png" width="128" alt="Trail app icon" />
</p>

<h1 align="center">Trail Backend</h1>

<p align="center">
  The server-side half of <a href="https://github.com/kibotu/trail">Trail</a> — a self-hosted micro link journal for people who miss chronological feeds and own servers.
  <br /><br />
  PHP, Slim 4, MariaDB. No Docker, no Kubernetes, no 47-service architecture. Just a backend that does its job and gets out of the way.
</p>

## Features

### Your timeline, your rules

- **Chronological feed** — Posts appear in the order they were created. No algorithm. No engagement optimization. Just time, doing its thing.
- **280-character posts** — Short enough to stay interesting, long enough to say something. Configurable if you disagree.
- **Full-text search** with relevance ranking (4+ chars) and fuzzy matching (1-3 chars)
- **Per-user pages** at `/@username` + a global feed for everyone
- **Cursor-based pagination** — Infinite scroll without the "page 47 of 200" nonsense

### Rich media

- **Up to 3 images per post** — JPEG, PNG, GIF (animated too), WebP, SVG, AVIF
- **Video support** — MP4, WebM, MOV (auto-converted to MP4 because browsers)
- **Chunked uploads** — 20MB max per file, uploaded in pieces so your connection can survive
- **WebP auto-conversion** — Everything gets optimized on the way in (except animated GIFs and SVGs, we're not monsters)
- **URL preview cards** — Automatic rich previews via Iframely with embed fallback. Paste a link, get a card.
- **Short URL resolution** — t.co, bit.ly, and friends get resolved to their actual destinations. No more mystery links.
- **OG preview images** — Auto-generated social sharing cards for your posts
- **Image proxy** — CORS-safe image serving for embedded content

### Social

- **Claps** — Medium-style appreciation, 1-50 per entry or comment. Because sometimes a simple "like" isn't enough, but 50 claps is.
- **Threaded comments** with their own clap counts
- **@mentions** — Tag other users, they get notified
- **Notifications** — Mentions, claps, comments. Configurable preferences. Mark read, mark all read, or ignore them like email.
- **View tracking** — Entry, comment, and profile views with 24-hour deduplication (so refreshing 47 times doesn't count as 47 views)

### Profiles & identity

- **Google OAuth 2.0** — Sign in with Google. No password to forget.
- **JWT + persistent API tokens** — Sliding refresh for sessions, 64-char hex tokens for API access
- **Custom profiles** — Avatar, header image, bio (160 chars), unique `@nickname`
- **Gravatar fallback** — No avatar uploaded? We'll check Gravatar before showing a grey circle.
- **Hash IDs** — Public entry URLs use short hashes instead of sequential IDs

### Data ownership

- **Data export** — Download everything. Your data, your backup. GDPR-friendly.
- **Account deletion** — Request deletion with a grace period, revert if you change your mind
- **RSS feeds** — Global feed + per-user feeds. Because RSS never died, it just got quieter.
- **Twitter/X migration** — Import your archive and pick up where Twitter left off. See [twitter-migration/](../twitter-migration/).

### Tags

- **Tag system** — Create, search, autocomplete, associate tags with entries
- **Tag filtering** — Browse entries by tag
- **Admin tools** — Batch updates, merge duplicate tags, cleanup

### Moderation

- **User muting** — Hide content from specific users (mute/unmute/status check)
- **Content reporting** — Report entries and comments
- **Content filters** — Configurable filtering rules

### Embeddable profiles

- **Profile widget** — Drop an iframe on any site, get a live Trail feed
- **Light/dark theme** with transparent background
- **Configurable** — Toggle header, search, pagination, entry count

### Admin dashboard

Not just a "delete things" panel. The admin dashboard is genuinely useful:

- **Duplicate detection** — Find and resolve duplicate entries (by text, URL, or both)
- **Broken link management** — Scan, recheck, dismiss, or bulk-delete broken links
- **Short link resolution** — Batch-resolve shortened URLs to their destinations
- **Tag management** — List, update, delete, merge tags across all entries
- **Error logs** — View, filter, get stats, cleanup
- **Storage stats** — See what's taking up space
- **Image pruning** — Find and remove orphaned uploads
- **View pruning** — Trim old view records
- **User management** — List, delete, revert deletions, remove all user content
- **Iframely usage tracking** — Monitor your API quota

### Security

- **Rate limiting** — Per-route, in-memory. Configurable requests per minute and hour.
- **Bot protection** — User-Agent validation and suspicious pattern blocking
- **XSS/CSRF/SQLi hardening** — Input sanitization, security headers, the works
- **CORS** — Configured for your deployment
- **`.htaccess` protection** — Sensitive files locked down

## Tech Stack

- **PHP 8.4+** with strict types everywhere
- **Slim Framework 4** (PSR-7) — Lean, fast, no magic
- **MariaDB/MySQL** (utf8mb4) — Battle-tested relational storage
- **JWT** via firebase/php-jwt — Token auth done right
- **Iframely** — URL preview enrichment (optional, has a free tier)
- **embed/embed** — Fallback URL metadata when Iframely isn't available

Full dependency list in [`composer.json`](composer.json).

## Prerequisites

- **PHP 8.4+** with extensions: `pdo_mysql`, `json`, `mbstring`, `curl`, `gd`
- **Composer** — [getcomposer.org](https://getcomposer.org/)
- **MariaDB or MySQL**
- **ffprobe** (optional) — For video dimension detection
- **lftp** — For FTP deployment (`brew install lftp` / `apt install lftp`)
- **Google Cloud Console project** — For OAuth credentials
- **Iframely API key** (optional) — For URL previews ([iframely.com](https://iframely.com/))

## Setup

### 1. Install dependencies

```bash
cd backend
composer install
```

### 2. Create database

```sql
CREATE DATABASE trail_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'trail_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON trail_db.* TO 'trail_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configure Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create or select a project
3. Enable **Google Identity Services**
4. **APIs & Services → Credentials** → Create **OAuth 2.0 Client ID** (Web application)
5. Add authorized redirect URI: `https://your-domain.com/admin/callback.php`
6. Copy the **Client ID** and **Client Secret**

### 4. Configure secrets.yml

```bash
cp secrets.yml.example secrets.yml
```

Edit `secrets.yml` — the important bits:

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

google_oauth:
  client_id: YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com
  client_secret: GOCSPX-YOUR_CLIENT_SECRET
  redirect_uri: https://your-domain.com/admin/callback.php

jwt:
  secret: your_256_bit_secret_key_at_least_32_chars
  expiry_hours: 168  # 7 days
```

See [`secrets.yml.example`](secrets.yml.example) for everything else — FTP, Iframely, rate limiting, bot protection, link health checking, and more.

### 5. Run locally

```bash
php -S localhost:8000 -t public
```

Visit http://localhost:8000. If it loads, you're good.

## Configuration

All configuration lives in `secrets.yml`. The highlights:

| Setting | Default | What it does |
|---------|---------|-------------|
| `app.max_text_length` | 280 | Character limit per post |
| `app.max_images_per_entry` | 3 | Media attachments per post |
| `security.rate_limit.requests_per_minute` | 180 | API rate limit (per route) |
| `security.rate_limit.requests_per_hour` | 3000 | Hourly rate limit |
| `jwt.expiry_hours` | 168 | Token lifetime (7 days) |
| `link_health.batch_size` | 50 | Broken link check batch size |
| `short_link_resolver.batch_size` | 1000 | Short URL resolution batch |

See [`secrets.yml.example`](secrets.yml.example) for the complete reference.

## Deployment

Deploy via FTP using `sync.sh` from the project root:

```bash
cd ..
./sync.sh
```

What happens:

1. Security configuration check
2. `composer install --no-dev` (production deps only)
3. FTP upload via lftp (excludes dev files, tests, uploads)
4. Database migrations run automatically
5. Temporary migration files cleaned up

Verify it worked:

- `https://your-domain.com` — Landing page
- `https://your-domain.com/admin` — OAuth login
- `https://your-domain.com/api/entries` — API returns JSON
- `https://your-domain.com/api/rss` — RSS feed

## Testing

```bash
composer test

# Or run suites individually
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Integration/
```

## API

Full REST API with public, authenticated, and admin endpoints. Entries, comments, claps, media uploads, profiles, notifications, tags, moderation, RSS — it's all there.

Generate an API token from your profile page after signing in.

**Live documentation:** https://trail.services.kibotu.net/api

## License

Apache 2.0
