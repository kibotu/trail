# Trail Backend - Development Guide

## Development Environment Setup

The Trail backend supports a development mode that bypasses Google OAuth for easier local development and testing.

### Quick Start

1. **Set Environment to Development**
   
   Edit `.env` file in the project root:
   ```bash
   APP_ENV=development
   ```

2. **Start Docker Services**
   ```bash
   cd backend
   make up
   ```

3. **Access the Application**
   - Admin Login: http://localhost:18000/admin/login.php
   - phpMyAdmin: http://localhost:18080

## Development Authentication

When `APP_ENV=development`, the login page displays a "Development Mode" section with pre-configured test users that bypass Google OAuth.

### Default Dev Users

The following test users are available (configured in `config.yml`):

| Email | Name | Role | Admin Access |
|-------|------|------|--------------|
| `dev@example.com` | Dev User | Admin | ✅ Yes |
| `admin@example.com` | Admin User | Admin | ✅ Yes |
| `user@example.com` | Regular User | User | ❌ No |

### How It Works

1. **Login Page**: When in development mode, the login page shows clickable dev user buttons
2. **One-Click Login**: Click any dev user to instantly log in without OAuth
3. **Session Creation**: A session is created in the database with a 24-hour expiry
4. **User Creation**: Dev users are automatically created in the database on first login

### Dev Login Endpoint

Direct access to dev login (development mode only):
```
GET /admin/dev-login.php?email={user_email}
```

Example:
```bash
curl http://localhost:18000/admin/dev-login.php?email=dev@example.com
```

### Customizing Dev Users

Edit `config.yml` to add or modify dev users:

```yaml
development:
  dev_users:
    - email: your-email@example.com
      name: Your Name
      is_admin: true
    - email: tester@example.com
      name: Test User
      is_admin: false
```

After changing the config, restart the container:
```bash
make restart
```

## Security Notes

⚠️ **Important Security Considerations:**

1. **Development Only**: Dev login is ONLY available when `APP_ENV=development`
2. **Production Safety**: In production (`APP_ENV=production`), the dev login endpoint returns an error
3. **No OAuth Required**: Dev mode completely bypasses Google OAuth
4. **Cookie Security**: Dev mode uses `secure=false` cookies to work with HTTP (localhost)

## Environment Variables

Key environment variables for development:

```bash
# Environment mode (development/production)
APP_ENV=development

# Database
DB_HOST=db
DB_NAME=d0459744
DB_USER=d0459744
DB_PASSWORD=your_password

# JWT (for API authentication)
JWT_SECRET=your_256_bit_secret_key_here

# Google OAuth (optional in dev mode)
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_CLIENT_REDIRECT_URI=http://localhost:18000/admin/callback.php
```

## Switching Between Development and Production

### Development Mode
```bash
# In .env file
APP_ENV=development

# Restart services
make restart
```

Features:
- ✅ Dev login available
- ✅ Detailed error messages
- ✅ No OAuth required
- ✅ HTTP cookies allowed

### Production Mode
```bash
# In .env file
APP_ENV=production

# Restart services
make restart
```

Features:
- ❌ Dev login disabled
- ✅ Google OAuth required
- ✅ HTTPS-only cookies
- ✅ Minimal error exposure

## Database Access

### phpMyAdmin
- URL: http://localhost:18080
- Server: `db`
- Username: From `DB_USER` in `.env`
- Password: From `DB_PASSWORD` in `.env`

### Direct MySQL Access
```bash
# Via Docker
make db-shell

# Or directly
docker exec -it trail-db mysql -u d0459744 -p d0459744
```

## Testing Dev Login

### Manual Testing
1. Open http://localhost:18000/admin/login.php
2. Verify "Development Mode" section is visible
3. Click on any dev user
4. Verify redirect to `/admin/` dashboard
5. Verify session cookie is set

### Automated Testing
```bash
# Test dev login endpoint
curl -v 'http://localhost:18000/admin/dev-login.php?email=dev@example.com'

# Should return:
# - HTTP 302 redirect to /admin/
# - Set-Cookie header with trail_session_id
```

## Troubleshooting

### Dev Login Not Showing
- Check `APP_ENV` is set to `development`
- Verify config.yml has `development.dev_users` section
- Restart containers: `make restart`

### "Dev login is only available in development mode" Error
- Verify `APP_ENV=development` in `.env`
- Check environment variable in container: `docker exec trail-web env | grep APP_ENV`

### Session Not Persisting
- Check browser cookies are enabled
- Verify session is created in database: `SELECT * FROM trail_sessions;`
- Check cookie domain settings (should be empty for localhost)

### Database Connection Issues
- Verify database container is running: `docker ps`
- Check database credentials in `.env`
- Test connection: `docker exec trail-web php -r "new PDO('mysql:host=db;dbname=d0459744', 'd0459744', 'password');"`

## Development Workflow

### Typical Development Session

```bash
# 1. Start services
cd backend
make up

# 2. Check logs
make logs

# 3. Access application
open http://localhost:18000/admin/login.php

# 4. Click dev user to login

# 5. Make code changes (auto-reloaded via volume mount)

# 6. View logs for errors
make logs

# 7. Stop services when done
make down
```

### Making Changes

- **PHP Files**: Changes are immediately reflected (volume mounted)
- **Config Files**: Require container restart: `make restart`
- **Environment Variables**: Require container recreation: `make down && make up`
- **Database Schema**: Run migrations manually or via phpMyAdmin

## Additional Resources

- [Main README](../README.md)
- [Docker Compose Configuration](docker-compose.yml)
- [Makefile Commands](Makefile)
- [API Documentation](../README.md#api-endpoints)

## Support

For issues or questions:
1. Check logs: `make logs`
2. Verify configuration: `make ps`
3. Review this guide
4. Check main README.md
