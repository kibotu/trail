#!/bin/bash
# Starts the Trail backend on :18000 for local dev and integration tests.
# (The DB must be up — see dev-db.sh.)
set -e
cd "$(dirname "$0")"
exec php -S localhost:18000 -t backend/public backend/router.php
