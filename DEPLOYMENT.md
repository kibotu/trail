# Deployment Guide

## Production Environment Constraints

**Available on production server:**
- PHP 8.4 (plain, no CLI tools)
- MariaDB database
- FTP access only

**NOT available on production:**
- ❌ Composer
- ❌ Git
- ❌ SSH access
- ❌ Command-line tools

## Deployment Strategy

Since production lacks Composer, we use a **build-then-deploy** workflow:
1. Build `vendor/` locally (not committed to Git)
2. Upload everything via FTP (including the built `vendor/`)
3. Production uses the uploaded dependencies

The local Docker environment matches production (PHP 8.4 + Apache + MariaDB) to ensure compatibility.

### Workflow

```
Git Repository
  ↓ (vendor/ NOT in Git)
  ↓
Build Step (Local)
  ↓ composer install --no-dev
  ↓ generates vendor/
  ↓
FTP Deployment Script
  ↓ uploads vendor/ + src/ + public/
  ↓
Production Server
  ↓ uses uploaded vendor/
  ✓ runs application
```

**Key Point**: `vendor/` is in `.gitignore` - it's built fresh on each deployment.

## Pre-Deployment Checklist

1. **Verify deployment readiness**:
   ```bash
   cd scripts
   python3 verify_deployment_readiness.py
   ```

2. **Test locally**:
   ```bash
   ./run.sh
   # Access http://localhost:18000 and verify everything works
   ```

3. **Ensure production .env is configured**:
   - `.env` file is NOT uploaded (excluded in deploy.py)
   - Manually create `.env` on production server via FTP
   - Use production values (different from `.env.docker`)

4. **Commit your code changes**:
   ```bash
   git add .
   git commit -m "Your changes"
   git push
   ```

**Note**: No need to manually run `composer install` - the deployment script does this automatically!

**See**: `TESTING_PRODUCTION_PARITY.md` for comprehensive testing guide

## Deployment Commands

### Full Deployment (Recommended)

Runs build, migrations, FTP upload, and health check:

```bash
cd scripts
uv run python full_deploy.py
```

This automatically:
1. ✅ Builds dependencies (`composer install --no-dev`)
2. ✅ Runs database migrations
3. ✅ Uploads files via FTP (including `vendor/`)
4. ✅ Performs health check

### Individual Steps

**1. Build dependencies only:**
```bash
cd scripts
uv run python prepare_deploy.py
```

**2. Database migrations only:**
```bash
cd scripts
uv run python db_migrate.py
```

**3. FTP upload only** (requires vendor/ to exist):
```bash
cd scripts
uv run python deploy.py
```

## What Gets Uploaded

✅ **Included in FTP upload:**
- `src/` - Application code
- `public/` - Web root (index.php, .htaccess) - **This is the DocumentRoot**
- `vendor/` - **Composer dependencies** (critical!) - **Not web-accessible (above DocumentRoot)**
- `config.yml` - Configuration file
- `composer.json` - Dependency list (for reference)
- `composer.lock` - Locked versions (for reference)

**Directory structure on production:**
```
/path/to/backend/          ← Upload here
├── public/                ← Apache DocumentRoot (web-accessible)
│   ├── index.php
│   ├── .htaccess
│   └── admin/
├── vendor/                ← NOT web-accessible (secure)
├── src/                   ← NOT web-accessible (secure)
├── config.yml             ← NOT web-accessible (secure)
└── .env                   ← NOT web-accessible (create manually)
```

❌ **Excluded from FTP upload:**
- `.env` - Environment variables (create manually on production)
- `tests/` - PHPUnit tests
- `docker-compose.yml`, `Dockerfile` - Docker files
- `.git/`, `.gitignore` - Git files
- `phpunit.xml` - Test configuration
- `node_modules/` - Not used in this project

## Production .env Configuration

Create `/path/to/production/.env` manually on the server via FTP:

```env
# Database
DB_HOST=localhost
DB_NAME=trail_production
DB_USER=trail_user
DB_PASSWORD=<strong_production_password>

# Google OAuth
GOOGLE_CLIENT_ID=<your_production_client_id>
GOOGLE_CLIENT_SECRET=<your_production_client_secret>

# Security
JWT_SECRET=<random_64_char_string>

# Application
APP_ENV=production
APP_DEBUG=false
```

**Important**: Never commit production `.env` to Git!

## Troubleshooting

### "vendor/ directory not found"

**Problem**: Deployment script can't find vendor directory.

**Solution**: The `full_deploy.py` script automatically builds it, but if you need to build manually:
```bash
cd scripts
uv run python prepare_deploy.py
```

Or directly:
```bash
cd backend
composer install --no-dev --optimize-autoloader
```

### "Class not found" errors on production

**Problem**: vendor/ wasn't uploaded or is incomplete.

**Causes**:
1. FTP upload was interrupted
2. Build step failed or was skipped

**Solution**:
```bash
# Re-run full deployment (includes build step)
cd scripts
uv run python full_deploy.py
```

Or manually:
```bash
# Build dependencies
cd scripts
uv run python prepare_deploy.py

# Deploy
uv run python deploy.py
```

### "composer.lock is newer than vendor/"

**Problem**: Dependencies were updated but not rebuilt.

**Solution**: Just re-run the deployment - it will rebuild automatically:
```bash
cd scripts
uv run python full_deploy.py
```

### Production shows different behavior than local

**Problem**: Environment mismatch.

**Check**:
1. PHP version matches (8.4)
2. Required PHP extensions enabled (see Dockerfile for list)
3. `.env` values are correct for production
4. Database credentials are correct
5. `APP_ENV=production` in production `.env`

## File Size Considerations

The `vendor/` directory can be large (10-50 MB depending on dependencies). FTP upload may take several minutes. The deployment script shows a progress bar.

**Current dependencies** (see `composer.json`):
- Slim Framework (~2 MB)
- Google API Client (~5 MB)
- Firebase JWT (~100 KB)
- Symfony YAML (~500 KB)
- PHPDotenv (~50 KB)

**Total vendor/ size**: ~10-15 MB

## Security Notes

1. **Never upload `.env`** - It's excluded by default
2. **Use strong passwords** in production `.env`
3. **Rotate JWT_SECRET** regularly
4. **Keep composer.lock** in version control for reproducible builds
5. **vendor/ is NOT in Git** - Built fresh on each deployment (cleaner, more secure)
6. **vendor/ is secure on production** - DocumentRoot points to `public/`, so `vendor/` is not web-accessible
7. **Production builds exclude dev dependencies** - PHPUnit and other dev tools are not uploaded

## Continuous Deployment

For automated deployments, the `full_deploy.py` script handles everything:

Example GitHub Action:
```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      
      - name: Setup Python
        uses: actions/setup-python@v4
        with:
          python-version: '3.11'
      
      - name: Install uv
        run: pip install uv
      
      - name: Deploy
        env:
          # Add your secrets in GitHub Settings
          FTP_HOST: ${{ secrets.FTP_HOST }}
          FTP_USER: ${{ secrets.FTP_USER }}
          FTP_PASSWORD: ${{ secrets.FTP_PASSWORD }}
        run: |
          cd scripts
          uv run python full_deploy.py
```

The script automatically:
1. ✅ Builds dependencies
2. ✅ Runs migrations
3. ✅ Uploads via FTP
4. ✅ Verifies health

## Rollback Procedure

If deployment fails:

1. **Database**: Restore from backup (manual)
2. **Files**: Re-upload previous version via FTP
3. **Quick rollback**: Keep a backup of working vendor/ directory

**Recommendation**: Test in staging environment first if available.

---

**Last Updated**: 2026-01-26
