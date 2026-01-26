# Dev Login - Quick Start

## TL;DR

Skip Google OAuth for local development with one-click dev login.

## Setup (30 seconds)

```bash
# 1. Set development mode
echo "APP_ENV=development" >> ../.env

# 2. Start services
make up

# 3. Open browser
open http://localhost:18000/admin/login.php
```

## Login

Click any dev user button:
- **dev@example.com** - Admin access
- **admin@example.com** - Admin access  
- **user@example.com** - Regular user

No OAuth popup, no credentials needed.

## How It Works

```
┌─────────────────────────────────────┐
│  Login Page (Development Mode)      │
├─────────────────────────────────────┤
│                                     │
│  [Sign in with Google]  ← Still works
│                                     │
│  ─────────────────────────────────  │
│                                     │
│  🔧 Development Mode                │
│                                     │
│  ┌─────────────────────────────┐   │
│  │ Dev User                    │   │
│  │ dev@example.com      [Admin]│   │
│  └─────────────────────────────┘   │
│                                     │
│  ┌─────────────────────────────┐   │
│  │ Admin User                  │   │
│  │ admin@example.com    [Admin]│   │
│  └─────────────────────────────┘   │
│                                     │
│  ┌─────────────────────────────┐   │
│  │ Regular User                │   │
│  │ user@example.com            │   │
│  └─────────────────────────────┘   │
│                                     │
└─────────────────────────────────────┘
```

## Production Safety

Dev login is **automatically disabled** when `APP_ENV=production`.

```bash
# Production mode (default)
APP_ENV=production  # Dev login blocked ✅

# Development mode
APP_ENV=development # Dev login enabled ✅
```

## Customizing Dev Users

Edit `config.yml`:

```yaml
development:
  dev_users:
    - email: your-email@test.com
      name: Your Name
      is_admin: true
```

Restart: `make restart`

## Troubleshooting

**Dev login not showing?**
```bash
# Check environment
docker exec trail-web env | grep APP_ENV
# Should show: APP_ENV=development

# If not, set it and restart
echo "APP_ENV=development" >> ../.env
make down && make up
```

**Can't login?**
```bash
# Check logs
make logs

# Check database
make db-shell
SELECT * FROM trail_sessions;
```

## Full Documentation

See [DEVELOPMENT.md](DEVELOPMENT.md) for complete guide.
