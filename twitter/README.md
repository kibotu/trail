# Twitter Archive Importer for Trail API

Migrate your Twitter archive to Trail API. One command. ~5 minutes for 2,788 tweets.

## Quick Start

```bash
./migrate.sh --jwt YOUR_TOKEN --archive twitter-backup.zip
```

Done. The script installs dependencies, extracts the ZIP, migrates tweets with original timestamps, caches progress, and cleans up.  

## Prerequisites

1. Twitter archive ZIP (Settings → Download archive, 24-48h wait)
2. JWT token from [trail.services.kibotu.net](https://trail.services.kibotu.net) (Google OAuth)
3. Python 3.7+ (script auto-installs `uv`)

## Usage

**Recommended (secure):**
```bash
export TRAIL_JWT_TOKEN="your_token"
./migrate.sh --archive twitter-backup.zip
```

**Test first:**
```bash
./migrate.sh --jwt TOKEN --archive backup.zip --dry-run --limit 10
```

**Resume interrupted migration:**
```bash
./migrate.sh --jwt TOKEN --archive backup.zip  # Automatic
```

**Options:**
- `--archive PATH` - ZIP file (required)
- `--jwt TOKEN` - Auth token (or `TRAIL_JWT_TOKEN` env)
- `--dry-run` - Test mode
- `--limit N` - Import first N tweets
- `--delay MS` - Rate limit (default: 100ms)
- `--keep-extracted` - Keep temp files
- `-v` - Verbose (show curl equivalents)

**Direct Python usage (extracted archives):**
```bash
uv run import_twitter_archive.py --jwt TOKEN --archive ./twitter-2026-01-30-xxx/
```

## What Gets Migrated

**Included:** Tweet text (280 chars), original timestamps, images (JPG/PNG/GIF/WebP, <20MB), favorites → claps (capped at 50)

**Excluded:** Videos, reply threading, retweet counts, metadata

## How It Works

1. Validates JWT token and ZIP file
2. Creates hash-based cache (`.migration_cache/<hash>.json`)
3. Extracts archive, verifies structure
4. Skips already-migrated tweets (from cache)
5. Uploads tweets with original timestamps via Trail API
6. Updates cache after each success
7. Cleans up temp files

**Resume:** Run the same command. Cache tracks progress automatically.

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Archive file not found" | Verify: `ls -la *.zip` |
| "Invalid archive structure" | Use official Twitter archive ZIP |
| "401 Unauthorized" | Get new JWT from trail.services.kibotu.net |
| "429 Too Many Requests" | Add `--delay 500` |
| Import failures | Images >20MB skipped, script continues |
| Start fresh | `rm -rf .migration_cache/` |

## Performance

2,788 tweets in 5-10 minutes (100ms delay). Uses `raw_upload: true`, connection pooling, automatic retries.

## Security

```bash
export TRAIL_JWT_TOKEN="your_token"  # Preferred
./migrate.sh --archive backup.zip
```

Never commit tokens. Already in `.gitignore`.

## API Details

**Payload format:**
```json
{
  "text": "Tweet content",
  "created_at": "Fri Nov 28 10:54:34 +0000 2025",
  "media": [{"data": "base64...", "filename": "photo.jpg"}],
  "raw_upload": true,
  "initial_claps": 25
}
```

**Engagement:** Favorites → claps (capped at 50)

**Why uv?** 10-100x faster than pip, zero config, inline dependencies (PEP 723)

## Links

- Trail API: https://trail.services.kibotu.net/api
- uv: https://docs.astral.sh/uv/
