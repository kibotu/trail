# Trail

A self-hosted micro link journal. Multi-user, chronological, yours.

**Live demo:** https://trail.services.kibotu.net

## Why

Personal link journaling without algorithmic feeds. Share what matters, own your data, control your timeline. Built for self-hosting hobbyists who want a simple, chronological space to collect links, thoughts, and images.

## Features

- 140-character posts with automatic URL card previews (powered by Iframely)
- Up to 3 images per post/comment (WebP-optimized, 20MB max each)
- Claps (Medium-style, 1-50 per entry), threaded comments, @mentions
- View counts on entries, comments, and profiles
- Per-user pages (`/@username`) and global chronological feed
- Full-text search with relevance ranking
- Customizable profiles (avatar, header image, bio)
- Notification system (claps, mentions)
- User muting and content reporting
- RSS feeds (global + per-user)
- Google OAuth 2.0 + persistent API tokens
- Twitter/X archive migration (one command)

## Stack

PHP 8.4+ • Slim 4 • MariaDB • JWT • Iframely API

## Quick Start

```bash
# 1. Clone and install
git clone https://github.com/kibotu/trail.git
cd trail/backend
composer install

# 2. Create database
mysql -u root -p
CREATE DATABASE trail_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 3. Configure
cp secrets.yml.example secrets.yml
# Edit secrets.yml with your database, Google OAuth, and JWT settings

# 4. Deploy
cd ..
./sync.sh
```

See [backend/README.md](backend/README.md) for complete deployment instructions.

## Twitter Migration

Migrate your Twitter/X archive to Trail in one command:

```bash
cd twitter
./migrate.sh --api-key YOUR_API_KEY --archive twitter-backup.zip
```

See [twitter/README.md](twitter/README.md) for details.

## API

Full REST API with public and authenticated endpoints. Generate an API token from your profile page.

**Documentation:** https://trail.services.kibotu.net/api

## Project Structure

```
trail/
├── backend/           # PHP API (Slim 4)
│   ├── public/        # Web root
│   ├── src/           # Controllers, Models, Services
│   └── secrets.yml    # Configuration (not in git)
├── twitter/           # Archive importer
│   ├── migrate.sh     # Migration script
│   └── README.md
├── migrations/        # SQL migrations
└── sync.sh            # Deployment script
```

## License

Apache 2.0
