# Trail - Link Journal Service

A multi-user link journaling service with Android app, PHP backend, MySQL database, Google OAuth authentication, and public RSS feed.

## Overview

Trail allows users to share links from their Android device with messages (max 280 characters), stored in a MySQL database, viewable via an admin interface and accessible through a public RSS feed.

## Features

- **Android App**: Share links directly from any app using Android's share intent
- **Google OAuth**: Secure authentication using Google Sign-In
- **PHP Backend**: RESTful API with Slim 4 framework
- **MySQL Database**: Optimized with production-grade indexes
- **Gravatar Integration**: Automatic profile images from email addresses
- **Admin Interface**: Responsive web interface for managing entries and users
- **RSS Feed**: Public RSS 2.0 feed for all entries or per-user feeds
- **Security**: Rate limiting, bot protection, CSRF tokens, JWT authentication
- **Docker Support**: Local development environment with Docker Compose

## Architecture

```
┌─────────────────┐
│  Android App    │
│  (Ktor + Koin)  │
└────────┬────────┘
         │ Google OAuth
         │ Share Intent
         ↓
┌─────────────────┐      ┌──────────────┐
│  PHP Backend    │─────→│    MySQL     │
│  (Slim 4)       │      │   Database   │
└────────┬────────┘      └──────────────┘
         │
         ├─→ Admin Interface (HTML/CSS)
         ├─→ RSS Feed (XML)
         └─→ API Endpoints (JSON)
```

## Technology Stack

### Backend
- PHP 8.4
- Slim 4 Framework
- MySQL 8.0+ with InnoDB
- JWT Authentication
- Google OAuth Verification
- Gravatar API
- Rate Limiting & Bot Protection
- Docker Compose for development

### Android
- Kotlin
- Jetpack Compose
- Ktor Client 3.0
- Kotlin Serialization
- Koin Dependency Injection
- Navigation 3
- Firebase Crashlytics
- Credential Manager (Google Sign-In)
- Min SDK: 23, Target SDK: 36

### Deployment
- Python UV for scripts
- FTP deployment automation
- Database migration management

## Prerequisites

- **PHP**: 8.4 or higher
- **Composer**: Latest version
- **MySQL**: 8.0 or higher
- **Docker**: Latest version (for local development)
- **Python**: 3.11 or higher
- **UV**: Python package manager
- **Android Studio**: Latest version (for Android app)
- **Java**: 17 (for Android development)

## Quick Start with Docker

1. **Clone the repository**
   ```bash
   cd /Users/jan.rabe/Documents/repos/kibotu/trail
   ```

2. **Copy environment files**
   ```bash
   cp .env.example .env
   cp config.yml.example config.yml
   ```

3. **Edit `.env` and `config.yml`** with your credentials

4. **Start Docker containers**
   ```bash
   cd backend
   docker-compose up -d
   ```

5. **Install PHP dependencies**
   ```bash
   docker-compose exec php composer install
   ```

6. **Run database migrations**
   ```bash
   cd ../scripts
   uv run python db_migrate.py
   ```

7. **Access the services**
   - Backend API: http://localhost:8000
   - phpMyAdmin: http://localhost:8080
   - Admin Interface: http://localhost:8000/admin

## Installation

### Automated Installation

Run the interactive installer:

```bash
cd scripts
uv run python install.py
```

The installer will:
- Check prerequisites
- Generate configuration files
- Install dependencies
- Set up Docker environment
- Run database migrations

### Manual Installation

#### 1. Database Setup

Create MySQL database and user:

```sql
CREATE DATABASE trail_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'trail_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON trail_db.* TO 'trail_user'@'localhost';
FLUSH PRIVILEGES;
```

#### 2. Configuration

Copy and edit configuration files:

```bash
cp .env.example .env
cp config.yml.example config.yml
```

Edit `.env` with your credentials:
- Database password
- FTP credentials
- Google OAuth credentials
- JWT secret (generate with: `openssl rand -base64 32`)

#### 3. Backend Setup

Install PHP dependencies:

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

#### 4. Run Migrations

```bash
cd ../scripts
uv run python db_migrate.py
```

#### 5. Android App Setup

1. Add `google-services.json` to `android/app/`
2. Update API base URL in `android/app/src/main/java/net/kibotu/trail/di/KoinModules.kt`
3. Build the app:
   ```bash
   cd android
   ./gradlew assembleDebug
   ```

## Configuration

### config.yml

The `config.yml` file is the single source of truth for all configuration. It supports environment variable substitution using `${VAR_NAME}` syntax.

Key sections:
- `database`: MySQL connection settings
- `docker`: Docker container configuration
- `ftp`: FTP deployment settings
- `google_oauth`: Google OAuth credentials
- `jwt`: JWT secret and expiry
- `security`: Rate limiting and bot protection
- `app`: Application URLs and RSS feed settings

### Environment Variables

Store sensitive data in `.env`:
- `DB_PASSWORD`: Database password
- `DOCKER_MYSQL_ROOT_PASSWORD`: Docker MySQL root password
- `FTP_USER` / `FTP_PASSWORD`: FTP credentials
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`: Google OAuth
- `JWT_SECRET`: JWT signing secret (256-bit minimum)

## Usage

### Android App

1. **Sign In**: Open the app and sign in with Google
2. **Share Link**: From any app (Chrome, Twitter, etc.), tap Share → Trail
3. **Add Message**: Enter a message (max 280 characters)
4. **Submit**: Your link is saved to your journal

### Admin Interface

Access at `http://your-domain.com/trail/admin`

Features:
- Dashboard with statistics
- View all entries with Gravatar avatars
- Edit/delete entries
- Manage users
- Mobile-responsive design

### RSS Feed

- **Global Feed**: `http://your-domain.com/trail/rss`
- **Per-User Feed**: `http://your-domain.com/trail/rss/{user_id}`

## Development

### Local Development with Docker

1. Start containers:
   ```bash
   cd backend
   docker-compose up -d
   ```

2. View logs:
   ```bash
   docker-compose logs -f
   ```

3. Stop containers:
   ```bash
   docker-compose down
   ```

### Running Tests

#### Backend Tests (PHPUnit)

```bash
cd backend
composer test
```

Or with Docker:

```bash
docker-compose exec php vendor/bin/phpunit
```

#### Android Tests

```bash
cd android
./gradlew test
./gradlew connectedAndroidTest
```

### Code Quality

Backend:
```bash
composer dump-autoload --optimize --classmap-authoritative
```

Android:
```bash
./gradlew lint
```

## Deployment

### Production Deployment

1. **Configure production settings** in `config.yml`

2. **Run full deployment**:
   ```bash
   cd scripts
   uv run python full_deploy.py
   ```

This will:
- Run database migrations
- Upload PHP files via FTP
- Verify backend health

### Manual Deployment Steps

1. **Database migrations**:
   ```bash
   uv run python db_migrate.py
   ```

2. **Deploy backend**:
   ```bash
   uv run python deploy.py
   ```

3. **Build Android APK**:
   ```bash
   cd android
   ./gradlew assembleRelease
   ```

## API Documentation

### Authentication

#### POST /api/auth/google

Authenticate with Google OAuth token.

**Request**:
```json
{
  "google_token": "google_id_token_here"
}
```

**Response**:
```json
{
  "jwt": "jwt_token_here",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "User Name",
    "gravatar_url": "https://gravatar.com/avatar/...",
    "is_admin": false
  }
}
```

### Entries (Authenticated)

#### POST /api/entries

Create a new entry.

**Headers**: `Authorization: Bearer {jwt}`

**Request**:
```json
{
  "url": "https://example.com/article",
  "message": "Check out this article!"
}
```

**Response**:
```json
{
  "id": 123,
  "created_at": "2026-01-26T10:00:00Z"
}
```

#### GET /api/entries

Get user's entries (paginated).

**Headers**: `Authorization: Bearer {jwt}`

**Query Parameters**:
- `page`: Page number (default: 1)
- `limit`: Items per page (default: 20, max: 50)

**Response**:
```json
{
  "entries": [...],
  "total": 100,
  "page": 1,
  "pages": 5,
  "limit": 20
}
```

### Public Endpoints

#### GET /rss

Global RSS feed (all users' entries).

#### GET /rss/{user_id}

Per-user RSS feed.

#### GET /health

Health check endpoint.

## Security

### Implemented Security Features

1. **Google OAuth**: Server-side token verification
2. **JWT Authentication**: 256-bit secret, 7-day expiry
3. **SQL Injection Protection**: Prepared statements (PDO)
4. **XSS Protection**: Output escaping with `htmlspecialchars()`
5. **CSRF Protection**: Tokens for admin forms
6. **Rate Limiting**: 60 req/min, 1000 req/hour per IP/user
7. **Bot Protection**: User-Agent validation, pattern blocking
8. **HTTPS Enforcement**: Redirect HTTP to HTTPS in production
9. **Security Headers**: X-Frame-Options, X-Content-Type-Options, CSP
10. **Input Validation**: URL format, message length (280 chars)

### Security Best Practices

- Never commit `config.yml`, `.env`, or `google-services.json`
- Use strong passwords (minimum 16 characters)
- Rotate JWT secret regularly
- Enable HTTPS in production
- Keep dependencies updated
- Review logs regularly
- Use least-privilege database user

## Performance

### Database Optimizations

- Composite index `(user_id, created_at DESC)` for user queries
- Index on `created_at DESC` for RSS feed generation
- Indexes on `google_id` and `email` for authentication
- `UNSIGNED INT` for better range and performance
- `utf8mb4` charset for full Unicode support
- InnoDB engine for ACID compliance

### Backend Optimizations

- RSS feed caching (5-minute TTL)
- API pagination (20-50 entries per page)
- Optimized Composer autoloader
- PHP OPcache enabled
- Connection pooling for PDO
- Pre-computed Gravatar hashes

### Android Optimizations

- Ktor HTTP/2 and connection pooling
- Response caching
- Coil for efficient image loading
- Lazy loading with LazyColumn
- ProGuard code shrinking

## Troubleshooting

### Backend Issues

**Error: "Configuration file not found"**
- Ensure `config.yml` exists in the project root
- Copy from `config.yml.example` if needed

**Error: "Environment variable not set"**
- Check `.env` file exists and contains all required variables
- Verify variable names match those in `config.yml`

**Database connection failed**
- Verify MySQL is running
- Check database credentials in `.env`
- Ensure database exists and user has permissions

**429 Too Many Requests**
- Rate limit exceeded
- Wait for the `Retry-After` header duration
- Check rate limit settings in `config.yml`

### Android Issues

**Google Sign-In not working**
- Ensure `google-services.json` is configured correctly
- Verify OAuth client ID in Google Cloud Console
- Check package name matches `net.kibotu.trail`

**App crashes on start**
- Check Crashlytics dashboard for error details
- Verify all dependencies are properly installed
- Ensure API base URL is configured correctly

**Share intent not working**
- Verify intent filter in `AndroidManifest.xml`
- Check app is installed and not disabled
- Ensure MIME type is `text/plain`

### Docker Issues

**Containers won't start**
- Check port conflicts (8000, 3306, 8080)
- Verify Docker is running
- Check `docker-compose logs` for errors

**Permission denied errors**
- Run: `chmod +x scripts/*.py`
- Check file ownership in mounted volumes

## Project Structure

```
trail/
├── README.md                    # This file
├── config.yml                   # Configuration (gitignored)
├── config.yml.example           # Configuration template
├── .env                         # Environment variables (gitignored)
├── .env.example                 # Environment template
├── .gitignore                   # Git ignore rules
├── backend/                     # PHP backend
│   ├── docker-compose.yml       # Docker dev environment
│   ├── Dockerfile               # PHP 8.4 container
│   ├── public/                  # Web root
│   │   └── index.php            # Entry point
│   ├── src/                     # Source code
│   │   ├── Config/              # Configuration loader
│   │   ├── Controllers/         # API controllers
│   │   ├── Database/            # Database connection
│   │   ├── Middleware/          # Middleware (auth, rate limit, etc.)
│   │   ├── Models/              # Data models
│   │   └── Services/            # Business logic
│   ├── templates/               # HTML templates
│   │   └── admin/               # Admin interface
│   ├── tests/                   # PHPUnit tests
│   ├── composer.json            # PHP dependencies
│   └── phpunit.xml              # Test configuration
├── android/                     # Android app
│   ├── app/
│   │   ├── src/main/java/net/kibotu/trail/
│   │   │   ├── TrailApplication.kt
│   │   │   ├── MainActivity.kt
│   │   │   ├── data/            # Data layer (API, models, repository)
│   │   │   ├── di/              # Koin modules
│   │   │   └── ui/              # UI layer (screens, ViewModels)
│   │   ├── build.gradle.kts     # App build configuration
│   │   └── google-services.json # Firebase config (gitignored)
│   ├── build.gradle.kts         # Project build configuration
│   └── settings.gradle.kts      # Project settings
├── migrations/                  # Database migrations
│   └── 001_initial_schema.sql   # Initial schema
└── scripts/                     # Python UV deployment scripts
    ├── pyproject.toml           # Python dependencies
    ├── install.py               # Interactive installer
    ├── db_migrate.py            # Database migrations
    ├── deploy.py                # FTP deployment
    └── full_deploy.py           # Full deployment pipeline
```

## License

MIT License - See LICENSE file for details

## Contributing

This is a personal project. Feel free to fork and adapt for your own use.

## Acknowledgments

- Built with idiomatic, excellent, and pragmatic code
- Inspired by the simplicity of Medium's sharing experience
- Channeling the spirit of Jake Wharton's clean Android development

## Support

For issues or questions:
1. Check the Troubleshooting section
2. Review logs: `docker-compose logs` or Crashlytics dashboard
3. Verify configuration in `config.yml` and `.env`

---

**Trail** - Share your links, build your journal.
