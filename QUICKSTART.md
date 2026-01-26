# Trail - Quick Start Guide

Get Trail up and running in 5 minutes!

## Prerequisites

- Docker installed
- Python 3.11+ with UV
- Android Studio (for app development)

## Step 1: Clone and Configure

```bash
cd /Users/jan.rabe/Documents/repos/kibotu/trail

# Copy configuration files
cp .env.example .env
cp config.yml.example config.yml

# Edit .env with your credentials
nano .env
```

## Step 2: Run Automated Installer

```bash
cd scripts
uv run python install.py
```

The installer will:
- ✓ Check prerequisites
- ✓ Generate configuration
- ✓ Install dependencies
- ✓ Start Docker containers
- ✓ Run database migrations

## Step 3: Access Services

- **Backend API**: http://localhost:8000
- **Admin Interface**: http://localhost:8000/admin
- **phpMyAdmin**: http://localhost:8080
- **RSS Feed**: http://localhost:8000/rss

## Step 4: Build Android App

```bash
cd android

# Add your google-services.json to app/
# Update API URL in app/src/main/java/net/kibotu/trail/di/KoinModules.kt

# Build
./gradlew assembleDebug

# Install on device
./gradlew installDebug
```

## Step 5: Test It Out

1. Open Trail app on your Android device
2. Sign in with Google
3. Share a link from Chrome to Trail
4. Add a message and submit
5. View your entry in the admin interface!

## Troubleshooting

**Docker issues?**
```bash
docker-compose down
docker-compose up -d
docker-compose logs -f
```

**Database issues?**
```bash
cd scripts
uv run python db_migrate.py
```

**Need to reset?**
```bash
docker-compose down -v  # Removes volumes
docker-compose up -d
```

## Next Steps

- Configure Google OAuth in Google Cloud Console
- Set up Firebase Crashlytics for Android
- Deploy to production with `uv run python full_deploy.py`
- Read the full README.md for detailed documentation

---

Happy journaling! 🚀
