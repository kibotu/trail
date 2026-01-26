# Docker Setup Guide (2026 Best Practices)

This project uses modern Docker practices including health checks, .env-based configuration, and the latest Docker Compose Specification. The architecture matches the production server setup with PHP + Apache.

## Prerequisites

- Docker Engine 29.x+ (API v1.52)
- Docker Compose v2+ (v5.x recommended)
- Minimum 4GB RAM allocated to Docker

## Quick Start (Development)

```bash
# 1. Configure environment (from project root)
cp .env.example .env
# Edit .env with your credentials

# 2. Setup backend (creates symlink to .env)
cd backend
ln -sf ../.env .env
./setup-docker.sh

# 3. Start all services
docker compose up -d

# 4. View logs
docker compose logs -f

# 5. Access services
# - Backend API: http://localhost:18000
# - phpMyAdmin: http://localhost:18080
# - MariaDB: localhost:13306
```

## Architecture

### Single Container Approach

The Dockerfile uses PHP 8.4 + Apache in a single container, matching the production server setup. This simplifies deployment and matches the architecture used across other services on the same server.

### Services

- **web** - PHP 8.4 + Apache with mod_rewrite, OPcache with JIT
- **db** - MariaDB 10.11 with optimized settings
- **phpmyadmin** - Database management (dev only)

## Environment Configuration

All sensitive data is managed through the `.env` file (single source of truth):

```bash
# .env file structure
PHP_VERSION=8.4
DB_PASSWORD=your_secure_password
DOCKER_MYSQL_ROOT_PASSWORD=root_password
JWT_SECRET=your_256_bit_secret_key
GOOGLE_CLIENT_ID=your_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_client_secret
FTP_USER=your_ftp_user
FTP_PASSWORD=your_ftp_password
```

**Setup environment:**

```bash
# From project root
cp .env.example .env
# Edit .env with your actual credentials

# Validate configuration (optional)
cd backend
./setup-docker.sh
```

**Security Notes:**
- `.env` file is excluded from git
- Never commit credentials to version control
- Use strong passwords (16+ characters)
- Rotate credentials regularly

## Health Checks

All services have health checks configured:

- **web**: HTTP check via curl on port 80
- **db**: MariaDB health check via healthcheck.sh
- **phpmyadmin**: HTTP check

Check service health:

```bash
docker compose ps
docker inspect trail-web --format='{{.State.Health.Status}}'
```

## Production Deployment

### Build Production Image

```bash
# Build production image
docker compose build

# Or specify PHP version
PHP_VERSION=8.4 docker compose build
```

### Deploy to Production

```bash
# Start production stack
docker compose up -d
```

### Production Features

- ✅ Apache + mod_rewrite for clean URLs
- ✅ OPcache with JIT compilation (PHP 8.4+)
- ✅ Health checks on all services
- ✅ .env-based configuration
- ✅ Optimized MariaDB settings

## Commands

### Development

```bash
# Start services
docker compose up -d

# Stop services
docker compose down

# Restart a service
docker compose restart web

# View logs
docker compose logs -f web

# Execute commands in container
docker compose exec web php -v
docker compose exec web composer install

# Run tests
docker compose exec web composer test

# Access MariaDB
docker compose exec db mysql -u trail_user -p trail_db
```

### Production

```bash
# Start production stack
docker compose up -d

# View resource usage
docker stats

# Backup database
docker compose exec db mysqldump -u root -p trail_db > backup.sql
```

### Maintenance

```bash
# Remove all containers and volumes
docker compose down -v

# Clean up unused resources
docker system prune -a --volumes

# Remove build cache
docker buildx prune

# Update images
docker compose pull
docker compose up -d
```

## Networking

### Bridge Network

All services communicate via a user-defined bridge network (`trail-network`):

- DNS-based service discovery
- Container name resolution (e.g., `php:9000`, `mysql:3306`)
- Network isolation from other Docker projects

### Port Mapping

**Development:**
- 18000 → web:80 (HTTP)
- 18080 → phpmyadmin:80 (phpMyAdmin)
- 13306 → db:3306 (MariaDB)

*Note: Non-standard ports (18xxx, 13xxx) are used to avoid conflicts with other services*

**Production:**
- 80 → web:80 (HTTP)
- 443 → web:443 (HTTPS with reverse proxy)
- MySQL not exposed (internal only)

## Performance Optimization

### PHP + Apache

- OPcache enabled with JIT compilation (PHP 8.4+)
- mod_rewrite for clean URLs
- Apache compression enabled
- Optimized memory limits (256M)

### MariaDB

- InnoDB optimized settings
- utf8mb4 character set
- Connection pool configured
- Health checks enabled

## Security

### Container Security

- ✅ .env-based secrets management
- ✅ No credentials in version control
- ✅ Minimal package installation
- ✅ Regular security updates

### Network Security

- ✅ .htaccess protection for sensitive files
- ✅ Rate limiting via PHP middleware
- ✅ JWT-based authentication
- ✅ Google OAuth integration

### Best Practices

- ✅ Health checks on all services
- ✅ Proper dependency ordering
- ✅ .env as single source of truth
- ✅ Matches production architecture

## Troubleshooting

### Container won't start

```bash
# Check logs
docker compose logs web

# Check health status
docker compose ps

# Inspect container
docker inspect trail-web
```

### Permission denied

```bash
# Fix file permissions
docker compose exec web chown -R www-data:www-data /var/www/html
```

### Database connection failed

```bash
# Verify MariaDB is healthy
docker compose ps db

# Check MariaDB logs
docker compose logs db

# Test connection
docker compose exec db mysql -u trail_user -p -e "SELECT 1"
```

### Port already in use

```bash
# Find process using port
lsof -i :8000

# Change port in docker-compose.yml
ports:
  - "8001:80"  # Use different host port
```

### Build cache issues

```bash
# Force rebuild without cache
docker compose build --no-cache

# Remove all build cache
docker builder prune -a
```

## Monitoring

### Health Checks

```bash
# View all service health
docker compose ps

# Watch health status
watch -n 2 'docker compose ps'
```

### Resource Usage

```bash
# Real-time stats
docker stats

# Container inspect
docker inspect trail-php --format='{{.State.Health}}'
```

### Logs

```bash
# Follow all logs
docker compose logs -f

# Specific service
docker compose logs -f web

# Last 100 lines
docker compose logs --tail=100 web
```

## Upgrading

### Docker Engine

```bash
# Check current version
docker version

# Update Docker Engine (varies by OS)
# macOS: Docker Desktop auto-updates
# Linux: apt-get update && apt-get upgrade docker-ce
```

### Images

```bash
# Pull latest images
docker compose pull

# Rebuild with latest base images
docker compose build --pull

# Restart with new images
docker compose up -d
```

## References

- [Docker Engine API v1.52](https://docs.docker.com/reference/api/engine/version/v1.52/)
- [Docker Compose Specification](https://docs.docker.com/reference/compose-file/)
- [BuildKit Documentation](https://docs.docker.com/build/buildkit/)
- [Multi-stage Builds](https://docs.docker.com/build/building/multi-stage/)
- [Docker Security](https://docs.docker.com/engine/security/)

---

**Last Updated**: January 2026 | Docker Engine 29.x | Compose v5.x
