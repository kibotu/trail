# Testing Production Parity

## Overview

This guide helps you verify that your local Docker environment matches production and that deployment will work correctly.

## Quick Test

```bash
# Run all checks
cd scripts
python3 verify_deployment_readiness.py
```

## Detailed Tests

### 1. PHP Version Check

**Production**: PHP 8.4.16-nmm1  
**Docker**: Should be PHP 8.4.x

```bash
# Check Docker PHP version
docker compose exec backend php -v

# Should output: PHP 8.4.x
```

✅ **Pass**: Version starts with "PHP 8.4"  
❌ **Fail**: Different version - update Dockerfile

### 2. PHP Extensions Check

**Required extensions** (from `composer.json` dependencies):
- curl (Google API Client)
- openssl (JWT, HTTPS)
- mysqli (Database)
- pdo_mysql (Database)
- mbstring (String handling)
- json (Data serialization)
- gd (Image manipulation)
- zip (Compression)
- bcmath (Math)
- intl (Internationalization)
- opcache (Performance)

```bash
# Check Docker extensions
docker compose exec backend php -m

# Or use the verification script
./scripts/verify_php_extensions.sh
```

✅ **Pass**: All required extensions listed  
❌ **Fail**: Missing extensions - update Dockerfile and rebuild

### 3. Vendor Directory Check

**Requirement**: `vendor/` must exist and contain all dependencies

```bash
# Check vendor exists
ls -la backend/vendor/

# Verify dependencies
cd scripts
python3 verify_vendor.py
```

✅ **Pass**: All packages present, autoload.php exists  
❌ **Fail**: Run `composer install` in backend/

### 4. Git Configuration Check

**Requirement**: `vendor/` should NOT be in Git

```bash
# Check .gitignore
grep "backend/vendor" .gitignore

# Verify vendor is not tracked
git status backend/vendor/
# Should say: "nothing to commit"
```

✅ **Pass**: vendor/ in .gitignore and not tracked  
❌ **Fail**: Add to .gitignore and commit

### 5. Environment Configuration

**Requirement**: `.env` and `config.yml` must exist

```bash
# Check files exist
ls -la .env config.yml

# Verify required variables
grep -E "DB_|GOOGLE_|JWT_" .env
```

✅ **Pass**: Files exist with required variables  
❌ **Fail**: Copy from examples and configure

### 6. Docker Services Check

**Requirement**: All services running

```bash
# Check services
docker compose ps

# Should show:
# - backend (running)
# - db (running)
# - phpmyadmin (running)
```

✅ **Pass**: All services "Up"  
❌ **Fail**: Run `./run.sh` or `docker compose up -d`

### 7. Database Connection Test

**Requirement**: Backend can connect to database

```bash
# Test connection
docker compose exec backend php -r "
  \$mysqli = new mysqli('db', 'trail_user', getenv('DB_PASSWORD'), 'trail');
  if (\$mysqli->connect_error) {
    echo 'Failed: ' . \$mysqli->connect_error;
    exit(1);
  }
  echo 'Success: Connected to database';
"
```

✅ **Pass**: "Success: Connected to database"  
❌ **Fail**: Check DB credentials in .env

### 8. Composer Dependencies Test

**Requirement**: All Composer packages loadable

```bash
# Test autoload
docker compose exec backend php -r "
  require '/var/www/html/vendor/autoload.php';
  echo 'Success: Autoload works';
"
```

✅ **Pass**: "Success: Autoload works"  
❌ **Fail**: Run `composer install` in backend/

### 9. Application Test

**Requirement**: Application loads without errors

```bash
# Start services
./run.sh

# Test endpoints
curl http://localhost:18000/health
curl http://localhost:18000/api/entries
```

✅ **Pass**: Both return valid responses  
❌ **Fail**: Check logs: `docker compose logs backend`

### 10. Production Comparison

**Requirement**: Docker matches production phpinfo

Compare these key values:

| Item | Production | Docker | Match |
|------|-----------|--------|-------|
| PHP Version | 8.4.16 | 8.4.x | ✅ |
| mysqli | ✅ | ✅ | ✅ |
| pdo_mysql | ✅ | ✅ | ✅ |
| mbstring | ✅ | ✅ | ✅ |
| gd | ✅ | ✅ | ✅ |
| curl | ✅ | ✅ | ✅ |
| openssl | ✅ | ✅ | ✅ |
| json | ✅ | ✅ | ✅ |
| opcache | ✅ | ✅ | ✅ |

See `PHP_EXTENSIONS_COMPARISON.md` for full comparison.

## Automated Test Suite

Run all tests at once:

```bash
cd scripts

# 1. Check deployment readiness
python3 verify_deployment_readiness.py

# 2. Check vendor integrity
python3 verify_vendor.py

# 3. Check PHP extensions (requires Docker running)
./verify_php_extensions.sh
```

## Pre-Deployment Checklist

Before running `full_deploy.py`, verify:

- [ ] All automated tests pass
- [ ] Docker services running
- [ ] `vendor/` directory exists
- [ ] `vendor/` NOT in Git
- [ ] `.env` configured for production
- [ ] `config.yml` configured for production
- [ ] Code changes committed to Git
- [ ] Local tests pass (`./run.sh` and manual testing)

## Troubleshooting

### "Extension not found"

**Problem**: PHP extension missing in Docker

**Solution**:
```bash
# Add to Dockerfile
RUN docker-php-ext-install extension_name

# Rebuild
docker compose build
docker compose up -d
```

### "vendor/ not found"

**Problem**: Dependencies not installed

**Solution**:
```bash
cd backend
composer install
```

### "Class not found"

**Problem**: Autoload not working

**Solution**:
```bash
cd backend
composer dump-autoload
```

### "Database connection failed"

**Problem**: Database not accessible

**Solution**:
```bash
# Check database is running
docker compose ps db

# Check credentials
docker compose exec backend php -r "echo getenv('DB_PASSWORD');"

# Restart database
docker compose restart db
```

### "Docker not running"

**Problem**: Docker daemon not started

**Solution**:
```bash
# Start Docker Desktop (macOS/Windows)
# Or start Docker service (Linux)
sudo systemctl start docker

# Then start services
docker compose up -d
```

## Production Testing

After deployment, test on production:

### 1. Health Check

```bash
curl https://your-domain.com/health
```

Expected: `{"status":"ok"}`

### 2. Admin Login

Visit: `https://your-domain.com/admin/login.php`

Expected: Google OAuth login page

### 3. API Endpoints

```bash
# Should require authentication
curl https://your-domain.com/api/entries
```

Expected: 401 Unauthorized (correct - needs auth)

### 4. RSS Feed

```bash
curl https://your-domain.com/rss
```

Expected: Valid RSS XML

### 5. Check Logs

Via FTP or hosting panel, check:
- PHP error logs
- Apache error logs

Expected: No critical errors

## Continuous Monitoring

Set up monitoring for:

1. **Uptime**: Is the site accessible?
2. **Response Time**: Is it fast enough?
3. **Error Rate**: Any 500 errors?
4. **Database**: Connection pool healthy?

Tools:
- UptimeRobot (free)
- Pingdom
- New Relic
- Your hosting provider's monitoring

## Summary

**Before Deployment**:
1. ✅ Run `verify_deployment_readiness.py`
2. ✅ Run `verify_vendor.py`
3. ✅ Run `verify_php_extensions.sh`
4. ✅ Test locally (`./run.sh`)

**After Deployment**:
1. ✅ Test health endpoint
2. ✅ Test admin login
3. ✅ Test API endpoints
4. ✅ Check error logs

**Regular Checks**:
1. ✅ Monitor uptime
2. ✅ Check error logs weekly
3. ✅ Update dependencies monthly
4. ✅ Test backups quarterly

---

**Last Updated**: 2026-01-26
