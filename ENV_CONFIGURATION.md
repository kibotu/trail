# Environment Configuration Guide

This document explains how Trail uses `.env` as the single source of truth for all configuration and credentials.

## Philosophy

**Single Source of Truth**: All credentials and configuration are managed through the `.env` file. This approach:
- ✅ Simplifies configuration management
- ✅ Reduces duplication
- ✅ Makes it easy to understand what's configured
- ✅ Works consistently across all environments
- ✅ No separate secret files to manage

## File Structure

### `.env` (Active Configuration)
Your actual credentials - **NEVER commit to git**

```bash
# Database
DB_NAME=trail_db
DB_USER=trail_user
DB_PASSWORD=your_secure_password_here

# Docker
DOCKER_MYSQL_ROOT_PASSWORD=root_password_here

# FTP
FTP_USER=your_ftp_username
FTP_PASSWORD=your_ftp_password

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_google_client_secret

# JWT
JWT_SECRET=your_256_bit_secret_key_here_at_least_32_characters_long
```

### `.env.example` (Template)
Template with placeholder values - **Safe to commit to git**

```bash
# Database
DB_NAME=trail_db
DB_USER=trail_user
DB_PASSWORD=your_secure_password_here

# Docker
DOCKER_MYSQL_ROOT_PASSWORD=root_password_here

# FTP
FTP_USER=your_ftp_username
FTP_PASSWORD=your_ftp_password

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_google_client_secret

# JWT
JWT_SECRET=your_256_bit_secret_key_here_at_least_32_characters_long
```

## Setup Process

### First Time Setup

```bash
# 1. Copy the example file (from project root)
cp .env.example .env

# 2. Edit with your actual credentials
nano .env  # or use your preferred editor

# 3. Create symlink for Docker Compose
cd backend
ln -sf ../.env .env

# 4. Validate configuration (optional)
./setup-docker.sh

# 5. Start services
docker compose up -d
```

### Validation Script

The `setup-docker.sh` script validates your configuration:

```bash
cd backend
./setup-docker.sh
```

**What it checks:**
- ✅ `.env` file exists
- ✅ All required variables are set
- ✅ Shows masked preview of credentials
- ✅ Creates `.env` from `.env.example` if missing

## How It Works

### Docker Compose Integration

Docker Compose automatically loads `.env` files:

```yaml
services:
  php:
    environment:
      DB_PASSWORD: ${DB_PASSWORD}
      JWT_SECRET: ${JWT_SECRET}
    env_file:
      - ../.env
```

**Benefits:**
- Variables available as environment variables in containers
- Can be referenced with `${VAR_NAME}` syntax
- Automatic loading by Docker Compose
- No manual secret file creation needed

### Application Integration

Your PHP application reads from environment variables:

```php
// config.yml
database:
  password: ${DB_PASSWORD}

jwt:
  secret: ${JWT_SECRET}
```

The application loads `.env` via `vlucas/phpdotenv`:

```php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$password = $_ENV['DB_PASSWORD'];
```

## Environment-Specific Configuration

### Development

```bash
# .env (development)
DB_PASSWORD=dev_password
DOCKER_MYSQL_ROOT_PASSWORD=dev_root
JWT_SECRET=dev_jwt_secret_at_least_32_characters_long
```

### Production

```bash
# .env (production)
DB_PASSWORD=strong_prod_password_16_chars_minimum
DOCKER_MYSQL_ROOT_PASSWORD=strong_root_password_16_chars
JWT_SECRET=production_jwt_secret_256_bits_minimum_32_chars
```

**Deployment:**
- Never copy `.env` to production
- Create production `.env` directly on server
- Use strong, unique passwords
- Consider using a secrets manager (AWS Secrets Manager, HashiCorp Vault)

## Security Best Practices

### File Permissions

```bash
# Restrict access to .env file
chmod 600 .env

# Verify permissions
ls -la .env
# Should show: -rw------- (owner read/write only)
```

### Git Protection

`.gitignore` excludes `.env`:

```gitignore
.env
config.yml
```

**Verify:**
```bash
git status
# .env should NOT appear in untracked files
```

### Password Requirements

- **Minimum length**: 16 characters
- **Complexity**: Mix of uppercase, lowercase, numbers, symbols
- **Uniqueness**: Different password for each service
- **JWT Secret**: At least 32 characters (256 bits)

**Generate strong passwords:**
```bash
# Random password (32 chars)
openssl rand -base64 32

# JWT secret (64 chars)
openssl rand -base64 64 | tr -d '\n'
```

## Variable Reference

### Required Variables

| Variable | Description | Example | Min Length |
|----------|-------------|---------|------------|
| `DB_NAME` | MariaDB database name | `trail_db` | - |
| `DB_USER` | MariaDB username | `trail_user` | - |
| `DB_PASSWORD` | MariaDB user password | `secure_pass_123` | 16 chars |
| `DOCKER_MYSQL_ROOT_PASSWORD` | MariaDB root password | `root_pass_456` | 16 chars |
| `JWT_SECRET` | JWT signing key | `256_bit_secret...` | 32 chars |
| `GOOGLE_CLIENT_ID` | OAuth client ID | `123.apps.googleusercontent.com` | - |
| `GOOGLE_CLIENT_SECRET` | OAuth secret | `GOCSPX-...` | - |

### Optional Variables

| Variable | Description | Default | Required For |
|----------|-------------|---------|--------------|
| `FTP_USER` | FTP username | - | Deployment |
| `FTP_PASSWORD` | FTP password | - | Deployment |

## Troubleshooting

### Variable Not Found

**Error:** `Environment variable not set`

**Solution:**
```bash
# Check .env exists
ls -la .env

# Validate configuration
cd backend
./setup-docker.sh

# Check variable is set
grep "VAR_NAME" .env
```

### Permission Denied

**Error:** `Permission denied: .env`

**Solution:**
```bash
# Fix permissions
chmod 600 .env

# Verify owner
ls -la .env
```

### Docker Not Loading Variables

**Error:** Variables empty in container

**Solution:**
```bash
# Check env_file path in docker-compose.yml
# Should be: - ../.env (relative to backend/)

# Restart containers
docker compose down
docker compose up -d

# Verify variables in container
docker compose exec php env | grep DB_PASSWORD
```

### Validation Fails

**Error:** `Missing required environment variables`

**Solution:**
```bash
# Compare with example
diff .env .env.example

# Add missing variables
nano .env

# Re-validate
cd backend
./setup-docker.sh
```

## Migration from Secrets

If you're migrating from Docker secrets:

### Old Approach (Secrets)
```bash
# Multiple files
backend/secrets/mysql_password.txt
backend/secrets/jwt_secret.txt
backend/secrets/google_client_id.txt
# etc...
```

### New Approach (.env)
```bash
# Single file
.env
```

**Migration steps:**
1. Copy values from secret files to `.env`
2. Remove `secrets/` directory
3. Update `docker-compose.yml` (already done)
4. Restart containers

## Advanced Usage

### Multiple Environments

```bash
# Development
.env.development

# Staging
.env.staging

# Production
.env.production
```

**Usage:**
```bash
# Specify env file
docker compose --env-file .env.staging up -d
```

### Environment Overrides

```bash
# Override specific variable
DB_PASSWORD=override_pass docker compose up -d

# Or export first
export DB_PASSWORD=override_pass
docker compose up -d
```

### CI/CD Integration

```yaml
# GitHub Actions
- name: Create .env
  run: |
    cat > .env << EOF
    DB_PASSWORD=${{ secrets.DB_PASSWORD }}
    JWT_SECRET=${{ secrets.JWT_SECRET }}
    EOF
```

## Best Practices Summary

✅ **DO:**
- Use `.env` for all credentials
- Keep `.env` out of git
- Use strong, unique passwords
- Set file permissions to 600
- Validate with `setup-docker.sh`
- Use different credentials per environment
- Rotate credentials regularly

❌ **DON'T:**
- Commit `.env` to git
- Share `.env` via email/chat
- Use weak passwords
- Reuse passwords across services
- Store `.env` in public locations
- Use production credentials in development

## Resources

- [Docker Compose Environment Variables](https://docs.docker.com/compose/environment-variables/)
- [PHP dotenv Library](https://github.com/vlucas/phpdotenv)
- [OWASP Password Guidelines](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)

---

**Remember**: `.env` is your single source of truth. Keep it secure, keep it simple.
