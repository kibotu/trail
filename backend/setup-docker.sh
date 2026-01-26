#!/bin/bash
# Setup script for Trail Docker environment
# This script validates that .env file exists and has required variables

set -e

ENV_FILE="../.env"
ENV_EXAMPLE="../.env.example"

echo "🔍 Checking Docker environment setup..."
echo ""

# Check if .env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "❌ Error: .env file not found!"
    echo ""
    if [ -f "$ENV_EXAMPLE" ]; then
        echo "📝 Creating .env from .env.example..."
        cp "$ENV_EXAMPLE" "$ENV_FILE"
        echo "✅ Created .env file"
        echo ""
        echo "⚠️  IMPORTANT: Edit .env and set your actual credentials!"
        echo ""
    else
        echo "❌ Error: .env.example not found either!"
        exit 1
    fi
else
    echo "✅ .env file found"
fi

# Required environment variables
REQUIRED_VARS=(
    "DB_NAME"
    "DB_USER"
    "DB_PASSWORD"
    "DOCKER_MYSQL_ROOT_PASSWORD"
    "JWT_SECRET"
    "GOOGLE_CLIENT_ID"
    "GOOGLE_CLIENT_SECRET"
)

# Load .env file
export $(grep -v '^#' "$ENV_FILE" | xargs)

# Check required variables
MISSING_VARS=()
for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        MISSING_VARS+=("$var")
    fi
done

echo ""
if [ ${#MISSING_VARS[@]} -eq 0 ]; then
    echo "✅ All required environment variables are set"
    echo ""
    echo "📋 Configuration:"
    echo "   DB_NAME: ${DB_NAME}"
    echo "   DB_USER: ${DB_USER}"
    echo "   DB_PASSWORD: ${DB_PASSWORD:0:3}***"
    echo "   DOCKER_MYSQL_ROOT_PASSWORD: ${DOCKER_MYSQL_ROOT_PASSWORD:0:3}***"
    echo "   JWT_SECRET: ${JWT_SECRET:0:3}***"
    echo "   GOOGLE_CLIENT_ID: ${GOOGLE_CLIENT_ID:0:20}..."
    echo "   GOOGLE_CLIENT_SECRET: ${GOOGLE_CLIENT_SECRET:0:3}***"
    echo ""
    echo "✅ Setup complete! You can now run:"
    echo "   docker compose up -d"
    echo ""
else
    echo "❌ Missing required environment variables in .env:"
    for var in "${MISSING_VARS[@]}"; do
        echo "   - $var"
    done
    echo ""
    echo "Please edit $ENV_FILE and set these variables."
    exit 1
fi
