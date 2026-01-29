#!/bin/bash
# Cleanup orphaned images (not referenced by entries or users)
# Add to crontab to run weekly: 0 2 * * 0 /path/to/backend/cleanup-orphan-images.sh
# This runs every Sunday at 2 AM

cd "$(dirname "$0")"

# Cleanup images older than 7 days (configurable)
DAYS_OLD=${1:-7}

php src/Cron/CleanupOrphanImages.php "$DAYS_OLD"
