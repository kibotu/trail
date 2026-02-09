#!/bin/bash
set -e

# migrate.sh - Twitter to Trail API migration tool
# A comprehensive script that handles Twitter archive migration with caching and resume support

VERSION="1.0.0"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMP_DIR="${SCRIPT_DIR}/.migration_temp"
CACHE_DIR="${SCRIPT_DIR}/.migration_cache"
CACHE_FILE=""
ARCHIVE_PATH=""
JWT_TOKEN=""
KEEP_EXTRACTED=false
VERBOSE=false
DRY_RUN=false
LIMIT=""
DELAY="100"
EXTRACTED_DIR=""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print functions
print_error() {
    echo -e "${RED}❌ Error: $1${NC}" >&2
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_header() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  🐦 Twitter to Trail API Migration Tool v${VERSION}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
}

# Show usage
usage() {
    cat << EOF
Usage: $0 --archive ARCHIVE_ZIP [OPTIONS]

Required:
  --archive PATH          Path to Twitter archive ZIP file
  --jwt TOKEN            JWT authentication token (or set TRAIL_JWT_TOKEN env var)

Options:
  --keep-extracted       Keep extracted files after migration
  --dry-run             Test run without creating entries
  --limit N             Import only first N tweets
  --delay MS            Delay between requests in milliseconds (default: 100)
  -v, --verbose         Enable verbose output (show curl equivalents)
  -h, --help            Show this help message

Examples:
  # Basic usage
  $0 --jwt YOUR_TOKEN --archive twitter-backup.zip

  # With environment variable
  export TRAIL_JWT_TOKEN="your_token"
  $0 --archive twitter-backup.zip

  # Keep extracted files and verbose mode
  $0 --jwt TOKEN --archive backup.zip --keep-extracted -v

  # Resume interrupted migration (automatically detects cached progress)
  $0 --jwt TOKEN --archive backup.zip

EOF
    exit 1
}

# Check if uv is installed
check_uv() {
    if command -v uv &> /dev/null; then
        print_success "uv is already installed ($(uv --version))"
        return 0
    else
        print_warning "uv is not installed"
        return 1
    fi
}

# Install uv
install_uv() {
    print_info "Installing uv package manager..."
    
    if curl -LsSf https://astral.sh/uv/install.sh | sh; then
        print_success "uv installed successfully"
        
        # Add to PATH for current session
        export PATH="$HOME/.cargo/bin:$PATH"
        
        if command -v uv &> /dev/null; then
            print_success "uv is now available ($(uv --version))"
        else
            print_error "uv installation succeeded but command not found. Please restart your shell."
            exit 1
        fi
    else
        print_error "Failed to install uv. Please install manually: https://docs.astral.sh/uv/"
        exit 1
    fi
}

# Check Python version
check_python() {
    if ! command -v python3 &> /dev/null; then
        print_error "Python 3 is not installed. Please install Python 3.7 or higher."
        exit 1
    fi
    
    local python_version=$(python3 --version 2>&1 | awk '{print $2}')
    print_success "Python ${python_version} found"
}

# Validate inputs
validate_inputs() {
    print_info "Validating inputs..."
    
    # Check JWT token
    if [ -z "$JWT_TOKEN" ]; then
        if [ -n "$TRAIL_JWT_TOKEN" ]; then
            JWT_TOKEN="$TRAIL_JWT_TOKEN"
            print_info "Using JWT token from TRAIL_JWT_TOKEN environment variable"
        else
            print_error "JWT token required. Use --jwt TOKEN or set TRAIL_JWT_TOKEN environment variable"
            exit 1
        fi
    fi
    
    # Validate JWT token format (basic check)
    if [ ${#JWT_TOKEN} -lt 20 ]; then
        print_error "JWT token seems too short. Please check your token."
        exit 1
    fi
    
    # Check archive file
    if [ -z "$ARCHIVE_PATH" ]; then
        print_error "Archive file required. Use --archive PATH"
        exit 1
    fi
    
    if [ ! -f "$ARCHIVE_PATH" ]; then
        print_error "Archive file not found: $ARCHIVE_PATH"
        exit 1
    fi
    
    if [ ! -r "$ARCHIVE_PATH" ]; then
        print_error "Archive file is not readable: $ARCHIVE_PATH"
        exit 1
    fi
    
    # Check if it's a zip file
    if ! file "$ARCHIVE_PATH" | grep -q "Zip archive"; then
        print_error "File is not a valid ZIP archive: $ARCHIVE_PATH"
        exit 1
    fi
    
    print_success "All inputs validated"
}

# Calculate archive hash for cache
calculate_archive_hash() {
    local hash=$(shasum -a 256 "$ARCHIVE_PATH" | awk '{print $1}')
    echo "$hash"
}

# Setup cache
setup_cache() {
    local archive_hash=$(calculate_archive_hash)
    CACHE_FILE="${CACHE_DIR}/${archive_hash}.json"
    
    mkdir -p "$CACHE_DIR"
    
    if [ -f "$CACHE_FILE" ]; then
        print_info "Found existing cache: ${CACHE_FILE}"
        local migrated_count=$(python3 -c "import json; data=json.load(open('$CACHE_FILE')); print(len(data.get('migrated_tweets', {})))" 2>/dev/null || echo "0")
        print_info "Previously migrated: ${migrated_count} tweets"
    else
        print_info "Creating new cache file: ${CACHE_FILE}"
        echo '{"archive_hash":"'$archive_hash'","migrated_tweets":{},"stats":{"total_tweets":0,"migrated":0,"failed":0}}' > "$CACHE_FILE"
    fi
}

# Extract archive
extract_archive() {
    print_info "Extracting Twitter archive..."
    
    mkdir -p "$TEMP_DIR"
    
    # Extract to temp directory
    if ! unzip -q "$ARCHIVE_PATH" -d "$TEMP_DIR"; then
        print_error "Failed to extract archive"
        cleanup_on_error
        exit 1
    fi
    
    # Check if files were extracted to a subdirectory or directly
    if [ -d "${TEMP_DIR}/data" ]; then
        # Files extracted directly to temp dir
        EXTRACTED_DIR="$TEMP_DIR"
    else
        # Find the extracted directory (Twitter may use hash-based naming)
        EXTRACTED_DIR=$(find "$TEMP_DIR" -maxdepth 1 -type d -name "twitter-*" | head -n 1)
        
        if [ -z "$EXTRACTED_DIR" ]; then
            # Check if there's any subdirectory
            EXTRACTED_DIR=$(find "$TEMP_DIR" -maxdepth 1 -type d ! -path "$TEMP_DIR" | head -n 1)
        fi
    fi
    
    if [ -z "$EXTRACTED_DIR" ]; then
        print_error "Could not find extracted Twitter archive directory"
        cleanup_on_error
        exit 1
    fi
    
    print_success "Archive extracted to: ${EXTRACTED_DIR}"
    
    # Verify archive structure
    if [ ! -f "${EXTRACTED_DIR}/data/tweets.js" ]; then
        print_error "Invalid archive structure: missing data/tweets.js"
        print_info "Expected structure: data/tweets.js"
        print_info "Found in temp dir:"
        ls -la "$TEMP_DIR" 2>/dev/null || true
        cleanup_on_error
        exit 1
    fi
    
    print_success "Archive structure verified"
}

# Get skip IDs from cache
get_skip_ids() {
    if [ ! -f "$CACHE_FILE" ]; then
        echo ""
        return
    fi
    
    # Extract Twitter IDs from cache
    python3 -c "
import json
import sys
try:
    with open('$CACHE_FILE', 'r') as f:
        data = json.load(f)
    ids = list(data.get('migrated_tweets', {}).keys())
    print(','.join(ids))
except Exception as e:
    sys.stderr.write(f'Warning: Could not read cache: {e}\n')
    print('')
" 2>/dev/null || echo ""
}

# Run migration
run_migration() {
    print_info "Starting migration process..."
    
    # Get skip IDs from cache
    local skip_ids=$(get_skip_ids)
    local skip_count=0
    
    if [ -n "$skip_ids" ]; then
        skip_count=$(echo "$skip_ids" | tr ',' '\n' | wc -l | tr -d ' ')
        print_info "Skipping ${skip_count} already migrated tweets"
    fi
    
    # Build command
    local cmd="uv run ${SCRIPT_DIR}/import_twitter_archive.py"
    cmd="$cmd --jwt \"$JWT_TOKEN\""
    cmd="$cmd --archive \"$EXTRACTED_DIR\""
    cmd="$cmd --cache-file \"$CACHE_FILE\""
    
    if [ -n "$skip_ids" ]; then
        cmd="$cmd --skip-ids \"$skip_ids\""
    fi
    
    if [ "$VERBOSE" = true ]; then
        cmd="$cmd -v"
    fi
    
    if [ "$DRY_RUN" = true ]; then
        cmd="$cmd --dry-run"
    fi
    
    if [ -n "$LIMIT" ]; then
        cmd="$cmd --limit $LIMIT"
    fi
    
    if [ -n "$DELAY" ]; then
        cmd="$cmd --delay $DELAY"
    fi
    
    print_info "Running import script..."
    if [ "$VERBOSE" = true ]; then
        print_info "Command: $cmd"
    fi
    
    # Run the import
    eval "$cmd"
    local exit_code=$?
    
    if [ $exit_code -eq 0 ]; then
        print_success "Migration completed successfully"
    else
        print_error "Migration failed with exit code: $exit_code"
        return $exit_code
    fi
}

# Cleanup on error
cleanup_on_error() {
    if [ -d "$TEMP_DIR" ]; then
        print_info "Cleaning up temporary files..."
        rm -rf "$TEMP_DIR"
    fi
}

# Cleanup after success
cleanup() {
    if [ "$KEEP_EXTRACTED" = false ]; then
        if [ -d "$TEMP_DIR" ]; then
            print_info "Cleaning up temporary files..."
            rm -rf "$TEMP_DIR"
            print_success "Temporary files removed"
        fi
    else
        print_info "Keeping extracted files at: ${TEMP_DIR}"
    fi
}

# Show summary
show_summary() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  📊 Migration Summary"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if [ -f "$CACHE_FILE" ]; then
        python3 -c "
import json
try:
    with open('$CACHE_FILE', 'r') as f:
        data = json.load(f)
    stats = data.get('stats', {})
    migrated = len(data.get('migrated_tweets', {}))
    print(f'  Total tweets:        {stats.get(\"total_tweets\", 0)}')
    print(f'  Migrated:            {migrated} ✅')
    print(f'  Failed:              {stats.get(\"failed\", 0)} ❌')
    print(f'  Cache file:          $CACHE_FILE')
except Exception as e:
    print(f'  Could not read cache: {e}')
" 2>/dev/null
    fi
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
}

# Main function
main() {
    print_header
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --archive)
                ARCHIVE_PATH="$2"
                shift 2
                ;;
            --jwt)
                JWT_TOKEN="$2"
                shift 2
                ;;
            --keep-extracted)
                KEEP_EXTRACTED=true
                shift
                ;;
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            --limit)
                LIMIT="$2"
                shift 2
                ;;
            --delay)
                DELAY="$2"
                shift 2
                ;;
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            -h|--help)
                usage
                ;;
            *)
                print_error "Unknown option: $1"
                usage
                ;;
        esac
    done
    
    # Check dependencies
    check_python
    
    if ! check_uv; then
        install_uv
    fi
    
    # Validate inputs
    validate_inputs
    
    # Setup cache
    setup_cache
    
    # Extract archive
    extract_archive
    
    # Run migration
    if run_migration; then
        cleanup
        show_summary
        print_success "🎉 Migration completed successfully!"
        exit 0
    else
        cleanup_on_error
        print_error "Migration failed. Check the errors above."
        exit 1
    fi
}

# Trap errors and cleanup
trap cleanup_on_error ERR INT TERM

# Run main
main "$@"
