#!/bin/bash
# Cleanup temporary upload files
# Add to crontab: */30 * * * * /path/to/backend/cleanup-temp-files.sh

cd "$(dirname "$0")"
php src/Cron/CleanupTempFiles.php
