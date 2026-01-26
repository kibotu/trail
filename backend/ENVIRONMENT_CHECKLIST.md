# Environment Switching Checklist

Quick reference for switching between development and production modes.

## Development Mode Checklist

Use this for local development and testing.

### Configuration
- [ ] Set `APP_ENV=development` in `.env`
- [ ] Verify `config.yml` has `development.dev_users` section
- [ ] Ensure `DB_HOST=db` for Docker environment

### Restart Services
```bash
cd backend
make restart
# or
make down && make up
```

### Verify
```bash
# Check environment
docker exec trail-web env | grep APP_ENV
# Expected: APP_ENV=development

# Test login page
curl -s http://localhost:18000/admin/login.php | grep "Development Mode"
# Expected: Should find "Development Mode"

# Test dev login
curl -I 'http://localhost:18000/admin/dev-login.php?email=dev@example.com' 2>&1 | grep "Location: /admin/"
# Expected: Should redirect to /admin/
```

### Features Available
- ✅ Dev login (one-click, no OAuth)
- ✅ Google OAuth (still works)
- ✅ Detailed error messages
- ✅ HTTP cookies (for localhost)
- ✅ All debugging features

### Access
- Login: http://localhost:18000/admin/login.php
- Dev Users: Click any user button
- OAuth: Click "Sign in with Google"

---

## Production Mode Checklist

Use this for deployment to production servers.

### Configuration
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Verify Google OAuth credentials are set
- [ ] Verify `GOOGLE_CLIENT_REDIRECT_URI` points to production URL
- [ ] Verify `JWT_SECRET` is strong (256-bit minimum)
- [ ] Verify database credentials are production values

### Security Review
- [ ] No test/dev credentials in `.env`
- [ ] `APP_ENV=production` confirmed
- [ ] HTTPS configured on server
- [ ] Rate limiting enabled in `config.yml`
- [ ] Bot protection enabled in `config.yml`

### Restart Services
```bash
cd backend
make restart
# or for production deployment
make prod
```

### Verify
```bash
# Check environment
docker exec trail-web env | grep APP_ENV
# Expected: APP_ENV=production

# Verify dev login is blocked
curl -s http://localhost:18000/admin/login.php | grep "Development Mode"
# Expected: Should NOT find "Development Mode"

# Test dev login is disabled
curl 'http://localhost:18000/admin/dev-login.php?email=dev@example.com'
# Expected: Error message about dev mode only
```

### Features Available
- ✅ Google OAuth (required)
- ✅ Secure HTTPS cookies
- ✅ Rate limiting
- ✅ Bot protection
- ❌ Dev login (disabled)
- ❌ Detailed error messages (minimal exposure)

### Access
- Login: https://your-domain.com/admin/login.php
- Auth: Google OAuth only
- No dev login available

---

## Quick Switch Commands

### Development → Production
```bash
# 1. Update .env
sed -i '' 's/APP_ENV=development/APP_ENV=production/' .env

# 2. Restart
cd backend && make restart

# 3. Verify
docker exec trail-web env | grep APP_ENV
```

### Production → Development
```bash
# 1. Update .env
sed -i '' 's/APP_ENV=production/APP_ENV=development/' .env

# 2. Restart
cd backend && make restart

# 3. Verify
docker exec trail-web env | grep APP_ENV
```

---

## Troubleshooting

### Wrong Mode Active

**Symptom**: Dev login showing in production (or vice versa)

**Solution**:
```bash
# Check current mode
docker exec trail-web env | grep APP_ENV

# Check .env file
cat .env | grep APP_ENV

# If mismatch, restart containers
cd backend
make down && make up
```

### Environment Variable Not Loading

**Symptom**: `APP_ENV` not set in container

**Solution**:
```bash
# Verify .env file exists
ls -la .env

# Verify docker-compose.yml includes env_file
grep "env_file" backend/docker-compose.yml

# Recreate containers (not just restart)
cd backend
make down && make up
```

### Dev Login Not Working

**Symptom**: Clicking dev user does nothing

**Solution**:
```bash
# Check logs
cd backend && make logs

# Verify dev_users in config
grep -A 10 "dev_users" backend/config.yml

# Test endpoint directly
curl -v 'http://localhost:18000/admin/dev-login.php?email=dev@example.com'
```

---

## Environment Comparison

| Feature | Development | Production |
|---------|-------------|------------|
| Dev Login | ✅ Enabled | ❌ Disabled |
| Google OAuth | ✅ Optional | ✅ Required |
| Cookie Security | HTTP OK | HTTPS Only |
| Error Messages | Detailed | Minimal |
| Rate Limiting | Optional | Enabled |
| Bot Protection | Optional | Enabled |
| Debug Mode | ✅ On | ❌ Off |

---

## Best Practices

### Development
1. Always use `APP_ENV=development` locally
2. Keep dev users in `config.yml` for easy testing
3. Use dev login for quick iterations
4. Test OAuth occasionally to ensure it still works

### Production
1. Always use `APP_ENV=production` on servers
2. Never commit production credentials to git
3. Use strong, unique `JWT_SECRET`
4. Enable all security features
5. Monitor logs regularly
6. Keep OAuth credentials secure

### Switching
1. Always verify mode after switching
2. Test login after switching
3. Check logs for errors
4. Verify security features in production

---

## Quick Reference

```bash
# Check current mode
docker exec trail-web env | grep APP_ENV

# Development mode
echo "APP_ENV=development" > .env
cd backend && make restart

# Production mode
echo "APP_ENV=production" > .env
cd backend && make restart

# Test login page
curl -s http://localhost:18000/admin/login.php | grep -E "Development Mode|Sign in with Google"

# Test dev login
curl -I 'http://localhost:18000/admin/dev-login.php?email=dev@example.com'
```
