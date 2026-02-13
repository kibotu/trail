# Trail Entry Rewriter

Rewrite Trail entry text in a witty, Jake Wharton-inspired style using opencode.

## Overview

This tool rewrites entry text to be more engaging while preserving the original URL and meaning. It supports:

- **Single entry mode**: Rewrite a specific entry by hash ID
- **Batch mode**: Process all entries (newest first) with resume support via cache

## Prerequisites

- Python 3.7+
- [uv](https://github.com/astral-sh/uv) (recommended) or pip
- [opencode](https://opencode.ai/docs/) installed at `/opt/homebrew/bin/opencode`
- Trail API key with permission to update entries

## Usage

### Single Entry Mode

```bash
# Preview (dry run)
uv run rewrite_entry.py --api-key YOUR_API_KEY --entry-id ABC123 --dry-run

# Actually update
uv run rewrite_entry.py --api-key YOUR_API_KEY --entry-id ABC123
```

### Batch Mode (All Entries)

```bash
# Dry run: preview first 5 entries
uv run rewrite_entry.py --api-key YOUR_API_KEY --dry-run

# Process all entries
uv run rewrite_entry.py --api-key YOUR_API_KEY

# Limit to 10 entries
uv run rewrite_entry.py --api-key YOUR_API_KEY --limit 10

# Resume after interruption (automatic via cache)
uv run rewrite_entry.py --api-key YOUR_API_KEY
```

### Using Environment Variable

```bash
export TRAIL_API_KEY=your_api_key
uv run rewrite_entry.py                    # batch mode
uv run rewrite_entry.py --entry-id ABC123  # single entry
```

## Options

| Option | Required | Description |
|--------|----------|-------------|
| `--entry-id` | No | Hash ID of single entry. If omitted, runs batch mode. |
| `--api-key` | No* | Trail API token (*or set `TRAIL_API_KEY` env var) |
| `--api-url` | No | API base URL (default: https://trail.services.kibotu.net/api) |
| `--cache-file` | No | Path to cache file (default: `.entry_rewrite_cache.json`) |
| `--dry-run` | No | Preview without updating (batch: first 5 only) |
| `--delay-ms` | No | Delay between API calls in ms (default: 2000) |
| `--limit` | No | Max entries to process in batch mode |
| `--model` | No | Override opencode model (e.g., `anthropic/claude-sonnet-4.5`) |
| `-v, --verbose` | No | Show verbose output for debugging |

## Examples

```bash
# Single entry: quick preview
uv run rewrite_entry.py --api-key $KEY --entry-id 000N88NS --dry-run

# Single entry: use Claude Sonnet
uv run rewrite_entry.py --api-key $KEY --entry-id 000N88NS --model anthropic/claude-sonnet-4.5

# Batch: process all, verbose
uv run rewrite_entry.py --api-key $KEY -v

# Batch: limit to 20 entries
uv run rewrite_entry.py --api-key $KEY --limit 20

# Clear cache and start fresh
rm .entry_rewrite_cache.json && uv run rewrite_entry.py --api-key $KEY
```

## Style Guide

The rewriter embodies these values (in order):

1. **Excellent** - High quality, thoughtful
2. **Idiomatic** - Natural, fluent English
3. **Correct** - Technically accurate
4. **Humble** - Understated, not preachy
5. **Positive** - Uplifting, constructive
6. **Optimistic** - Forward-looking
7. **Pragmatic** - Practical, actionable

### What It Does

- Rewrites text to be more engaging
- Preserves URLs exactly as provided
- Maintains technical accuracy
- Adds wit without being try-hard

### What It Avoids

- Corporate buzzwords
- Excessive exclamation points
- Emojis (unless original had them)
- Over-explanation
- Manufactured hype

## Safety Features

- URL preservation check: skips entry if URL appears missing from rewritten text
- 280 character limit: skips entries that exceed tweet length
- Entries without URLs are automatically skipped
- Dry-run mode for previewing changes
- Cache saves after each entry (resume-safe on Ctrl+C)
- Graceful shutdown with cache save on interrupt

## Troubleshooting

### "opencode not found"

Install opencode: https://opencode.ai/docs/

### "Access denied (not your entry or not admin)"

You need to either own the entry or have admin privileges to update it.

### "Entry not found"

Check that the hash ID is correct. You can find it in the entry URL or via the API.

### Start fresh (clear cache)

```bash
rm .entry_rewrite_cache.json
```
