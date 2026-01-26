# Development Environment Setup - Summary

## What Was Added

A complete development environment that bypasses Google OAuth for local development, making it easy to test and develop without external authentication dependencies.

## Key Features

### 1. **One-Click Dev Login**
- No Google OAuth popup required
- Pre-configured test users
- Instant session creation
- Works on localhost without HTTPS

### 2. **Environment-Based Configuration**
- `APP_ENV=development` - Dev login enabled
- `APP_ENV=production` - Dev login disabled (secure)
- Automatic environment detection

### 3. **Multiple Test Users**
Three pre-configured users with different roles:
- `dev@example.com` - Admin user
- `admin@example.com` - Admin user
- `user@example.com` - Regular user (non-admin)

### 4. **Production-Safe**
- Dev login automatically disabled in production
- No security risks when deployed
- OAuth still works in both modes

## Files Modified

### Configuration Files
- `.env` - Added `APP_ENV=development`
- `.env.example` - Added `APP_ENV` and `DB_HOST`
- `config.yml` - Added `environment` variable and `development` section
- `config.yml.example` - Same as config.yml
- `backend/config.yml` - Same as config.yml
- `backend/.env.docker` - Added dev environment variables
- `backend/docker-compose.yml` - Added `APP_ENV` environment variable

### PHP Files
- `backend/public/admin/login.php` - Added dev login UI section
- `backend/public/admin/dev-login.php` - **NEW** - Dev login handler

### Documentation
- `README.md` - Added development mode section
- `backend/DEVELOPMENT.md` - **NEW** - Complete development guide
- `backend/DEV_LOGIN_QUICK_START.md` - **NEW** - Quick reference
- `DEVELOPMENT_SETUP_SUMMARY.md` - **NEW** - This file

## How to Use

### Quick Start

```bash
# 1. Ensure APP_ENV is set to development
grep APP_ENV .env
# Should show: APP_ENV=development

# 2. Start services
cd backend
make up

# 3. Open login page
open http://localhost:18000/admin/login.php

# 4. Click any dev user to login
```

### Visual Guide

When you open the login page in development mode, you'll see:

```
┌────────────────────────────────────┐
│           Trail                    │
│      Link Journal Admin            │
│                                    │
│  [Sign in with Google]             │ ← OAuth still available
│                                    │
│  ────────────────────────────────  │
│                                    │
│  🔧 Development Mode               │
│                                    │
│  [Dev User]                 [Admin]│ ← Click to login
│  dev@example.com                   │
│                                    │
│  [Admin User]               [Admin]│
│  admin@example.com                 │
│                                    │
│  [Regular User]                    │
│  user@example.com                  │
│                                    │
└────────────────────────────────────┘
```

### Switching Modes

**Development Mode** (skip OAuth):
```bash
# In .env
APP_ENV=development

# Restart
cd backend && make restart
```

**Production Mode** (require OAuth):
```bash
# In .env
APP_ENV=production

# Restart
cd backend && make restart
```

## Configuration Details

### Dev Users Configuration

Located in `config.yml` under the `development` section:

```yaml
development:
  dev_users:
    - email: dev@example.com
      name: Dev User
      is_admin: true
    - email: admin@example.com
      name: Admin User
      is_admin: true
    - email: user@example.com
      name: Regular User
      is_admin: false
```

### Adding Custom Dev Users

1. Edit `config.yml`
2. Add new user to `development.dev_users` array
3. Restart containers: `cd backend && make restart`
4. User will appear on login page

Example:
```yaml
development:
  dev_users:
    - email: mytest@example.com
      name: My Test User
      is_admin: true
```

## Security Implementation

### How It's Secured

1. **Environment Check**: Dev login only works when `APP_ENV=development`
2. **Explicit Configuration**: Dev users must be explicitly listed in config
3. **Database Tracking**: Dev users are created with special Google ID prefix `dev_`
4. **Session Management**: Same secure session system as OAuth
5. **Production Disabled**: Attempting dev login in production returns error

### Dev Login Flow

```
User clicks dev user
    ↓
dev-login.php receives email
    ↓
Check APP_ENV === 'development'
    ↓ (if production, error)
Find user in config.yml dev_users
    ↓
Create/update user in database
    ↓
Create session (24hr expiry)
    ↓
Set session cookie
    ↓
Redirect to /admin/
```

## Testing

### Automated Tests

```bash
# Test environment
docker exec trail-web env | grep APP_ENV

# Test login page
curl -s http://localhost:18000/admin/login.php | grep "Development Mode"

# Test dev login
curl -v 'http://localhost:18000/admin/dev-login.php?email=dev@example.com'

# Test session cookie
curl -I 'http://localhost:18000/admin/dev-login.php?email=dev@example.com' | grep Set-Cookie
```

### Manual Testing

1. **Login Page Display**
   - Open http://localhost:18000/admin/login.php
   - Verify "Development Mode" section appears
   - Verify all 3 dev users are listed

2. **Dev Login**
   - Click "Dev User"
   - Verify redirect to /admin/
   - Verify you're logged in

3. **Session Persistence**
   - Close browser
   - Reopen http://localhost:18000/admin/
   - Verify still logged in (session persists)

4. **Production Mode**
   - Set `APP_ENV=production` in .env
   - Restart: `cd backend && make restart`
   - Open login page
   - Verify "Development Mode" section is NOT visible
   - Attempt dev login directly
   - Verify error: "Dev login is only available in development mode"

## Troubleshooting

### Dev Login Not Showing

**Problem**: Login page doesn't show dev users

**Solutions**:
```bash
# Check APP_ENV
docker exec trail-web env | grep APP_ENV
# Should show: APP_ENV=development

# If not set, add to .env
echo "APP_ENV=development" >> .env

# Restart containers
cd backend
make down && make up
```

### Can't Login

**Problem**: Clicking dev user doesn't work

**Solutions**:
```bash
# Check logs
cd backend
make logs

# Check database connection
docker exec trail-web php -r "new PDO('mysql:host=db;dbname=d0459744', 'd0459744', 'password');"

# Check sessions table
make db-shell
SELECT * FROM trail_sessions;
```

### Session Not Persisting

**Problem**: Logged out after refresh

**Solutions**:
- Check browser cookies are enabled
- Check cookie is being set: Browser DevTools → Application → Cookies
- Verify session in database: `SELECT * FROM trail_sessions WHERE email='dev@example.com';`

## Benefits

### For Developers
- ✅ No OAuth setup required for local dev
- ✅ Instant login, no popups
- ✅ Test different user roles easily
- ✅ Works offline
- ✅ No external dependencies

### For Testing
- ✅ Automated testing possible
- ✅ Multiple test users pre-configured
- ✅ Admin vs regular user testing
- ✅ Session management testing

### For Security
- ✅ Production-safe (auto-disabled)
- ✅ No OAuth credentials needed locally
- ✅ Same session security as OAuth
- ✅ Audit trail (dev users marked in DB)

## Next Steps

1. **Start developing**: `cd backend && make up`
2. **Read full guide**: [backend/DEVELOPMENT.md](backend/DEVELOPMENT.md)
3. **Quick reference**: [backend/DEV_LOGIN_QUICK_START.md](backend/DEV_LOGIN_QUICK_START.md)
4. **Customize users**: Edit `config.yml` development section

## Support

- **Full Documentation**: [backend/DEVELOPMENT.md](backend/DEVELOPMENT.md)
- **Quick Start**: [backend/DEV_LOGIN_QUICK_START.md](backend/DEV_LOGIN_QUICK_START.md)
- **Main README**: [README.md](README.md)
- **Logs**: `cd backend && make logs`
