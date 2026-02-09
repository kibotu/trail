# Trail Backend API

A lightweight, secure PHP backend for the Trail link journal app.

## Features

- 🔐 **Google OAuth Authentication** - Secure user authentication
- 🔑 **JWT Token Management** - Stateless session handling
- 📝 **Entry Management** - Create, read, update, delete entries
- 🔗 **URL Preview Cards** - Powered by iframe.ly API with embed library fallback
- 🛡️ **Security Hardening** - XSS prevention, rate limiting, input sanitization
- 📊 **RSS Feed** - Public feed of all entries
- 👤 **Gravatar Integration** - User avatars with Google photo fallback
- 🚀 **Production Ready** - Optimized for deployment

## Tech Stack

- **PHP 8.4+** - Modern PHP with strict types
- **Slim Framework 4** - Lightweight PSR-7 framework
- **MySQL/MariaDB** - Relational database
- **JWT** - Token-based authentication
- **iframe.ly API** - Primary URL metadata extraction
- **embed/embed** - Fallback URL metadata extraction

## Quick Start

### Development

```bash
cd backend

# Install dependencies (includes dev tools)
composer install

# Copy configuration
cp config.yml.example secrets.yml
# Edit secrets.yml with your settings

# Run tests
composer test

# Start development server
php -S localhost:8000 -t public
```

### Production Deployment

```bash
# From project root
./sync.sh
```

This will:
1. Install production dependencies (3.2MB vendor)
2. Upload to FTP server
3. Run database migrations
4. Verify deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for details.

## URL Preview Feature

When users post URLs, the backend automatically fetches metadata using **iframe.ly API** (with embed library fallback):

```php
POST /api/entries
{
  "text": "Check out https://github.com"
}

// Response includes preview data:
{
  "id": 123,
  "text": "Check out https://github.com",
  "preview_url": "https://github.com",
  "preview_title": "GitHub · Change is constant...",
  "preview_description": "Join the world's most widely adopted...",
  "preview_image": "https://images.ctfassets.net/...",
  "preview_site_name": "GitHub"
}
```

**Preview Strategy:**
1. **iframe.ly API** (Primary) - Fast, reliable metadata extraction (2000/month limit)
2. **Medium-specific handling** - Custom oEmbed + RSS for Medium articles
3. **embed/embed library** (Fallback) - Open Graph, Twitter Cards, oEmbed, JSON-LD

**Usage Tracking:**
- Monthly limit: 2000 iframe.ly API requests
- Automatic fallback when limit reached
- Email notification to admin
- Counter resets each month

See [IFRAMELY_INTEGRATION.md](../IFRAMELY_INTEGRATION.md) and [USAGE_TRACKING.md](../USAGE_TRACKING.md) for details.

## Testing

### Quick Test

Test iframe.ly integration:
```bash
php test_iframely.php
```

Test usage tracking:
```bash
php test_usage_tracker.php
```

### Unit Tests

```bash
composer test
# or
./vendor/bin/phpunit
```

### Specific Tests

```bash
# URL embed feature
./vendor/bin/phpunit tests/Unit/UrlEmbedServiceTest.php

# Integration tests (requires database)
./vendor/bin/phpunit tests/Integration/
```

See [TESTING.md](TESTING.md) for comprehensive testing guide.

## API Endpoints

### Public Endpoints

- `GET /` - Landing page
- `GET /api/entries/public` - List all entries (paginated)
- `GET /rss` - RSS feed

### Authentication

- `POST /api/auth/google` - Google OAuth login
- `POST /api/auth/refresh` - Refresh JWT token

### Protected Endpoints (Requires JWT)

- `GET /api/entries` - List user's entries
- `POST /api/entries` - Create entry (supports custom dates, inline media, initial claps)
- `PUT /api/entries/{id}` - Update entry
- `DELETE /api/entries/{id}` - Delete entry

### Admin Endpoints

- `GET /admin` - Admin dashboard
- `GET /admin/login` - Google OAuth login
- `GET /admin/logout` - Logout

## Advanced Entry Creation

The `/api/entries` endpoint supports advanced features for importing content:

### Custom Creation Dates
Import entries with their original timestamps (e.g., from Twitter/X):
```json
{
  "text": "Imported tweet",
  "created_at": "Fri Nov 28 10:54:34 +0000 2025"
}
```

### Inline Media Upload
Upload images directly in the request (base64 encoded):
```json
{
  "text": "Photo post",
  "media": [{
    "data": "base64_encoded_image...",
    "filename": "photo.jpg",
    "image_type": "post"
  }],
  "raw_upload": false
}
```

### Initial Engagement Metrics
Set initial clap counts when importing:
```json
{
  "text": "Popular post",
  "initial_claps": 25
}
```

See [API_EXAMPLES.md](../API_EXAMPLES.md) for complete API documentation.

## Project Structure

```
backend/
├── public/              # Web root
│   ├── index.php       # API entry point
│   ├── admin/          # Admin interface
│   └── .htaccess       # URL rewriting
├── src/
│   ├── Config/         # Configuration loader
│   ├── Controllers/    # Request handlers
│   ├── Database/       # Database connection
│   ├── Middleware/     # Auth, CORS, rate limiting
│   ├── Models/         # Data models
│   └── Services/       # Business logic
├── templates/          # HTML templates
├── tests/              # PHPUnit tests
├── vendor/             # Composer dependencies (3.2MB)
├── composer.json       # Dependencies
├── phpunit.xml         # Test configuration
└── test-url-embed.php  # Standalone test script
```

## Security Features

- ✅ XSS prevention (input sanitization)
- ✅ SQL injection protection (prepared statements)
- ✅ CSRF protection (JWT tokens)
- ✅ Rate limiting (configurable)
- ✅ Bot protection
- ✅ Secure headers (HSTS, CSP, etc.)
- ✅ URL validation (only http/https)
- ✅ Content sanitization

## Configuration

Edit `secrets.yml`:

```yaml
database:
  host: localhost
  name: trail_db
  user: trail_user
  password: your_password

google_oauth:
  client_id: your_client_id
  client_secret: your_client_secret

jwt:
  secret: your_256_bit_secret
  expiry_hours: 168

security:
  rate_limit:
    enabled: true
    requests_per_minute: 120
```

## Database Migrations

**IMPORTANT:** Migrations run automatically during deployment via `./sync.sh`.

```bash
# Deploy and run all pending migrations
./sync.sh
```

The sync script:
1. Detects pending migrations automatically
2. Executes them in order
3. Records successful migrations
4. Rolls back on failure

**Never run migrations manually.** Always use `./sync.sh`.

Latest migrations:
- `023_simplify_created_at.sql` - Simplified created_at (removed custom_created_at column)

See [MIGRATION_GUIDE.md](../MIGRATION_GUIDE.md) for details.

## Performance

- **Vendor Size**: 3.2MB (production, --no-dev)
- **API Response Time**: <50ms (typical)
- **URL Preview Fetch**: 60-100ms (cached by embed library)
- **Database Queries**: Optimized with indexes

## Dependencies

Production (3.2MB):
- `slim/slim` (^4.14) - Web framework
- `slim/psr7` (^1.7) - PSR-7 implementation
- `firebase/php-jwt` (^6.10) - JWT handling
- `symfony/yaml` (^7.2) - Config parsing
- `embed/embed` (^4.4) - URL metadata

Development only:
- `phpunit/phpunit` (^11.5) - Testing

## Documentation

### Deployment & Operations
- [DEPLOYMENT.md](../DEPLOYMENT.md) - Complete deployment guide
- [MIGRATION_GUIDE.md](../MIGRATION_GUIDE.md) - Database migration guide

### API Documentation
- [API_EXAMPLES.md](../API_EXAMPLES.md) - Complete API usage examples
- [CUSTOM_DATE_FEATURE_README.md](../CUSTOM_DATE_FEATURE_README.md) - Custom date entry feature

### Features
- [URL_PREVIEW_FEATURE.md](../URL_PREVIEW_FEATURE.md) - URL preview documentation
- [IMPLEMENTATION_SUMMARY.md](../IMPLEMENTATION_SUMMARY.md) - Technical implementation details

### Testing
- [TESTING.md](TESTING.md) - Testing guide

## License

See [LICENSE](../LICENSE)

## Support

For issues or questions, check the documentation or review the test files for usage examples.
