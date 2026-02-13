# Trail Entry Rewriter

Rewrite Trail entry text in a witty, Jake Wharton-inspired style using opencode.

## Overview

This tool takes a single entry by hash ID, fetches its content, and uses AI to rewrite the text to be more engaging while preserving the original URL and meaning.

## Prerequisites

- Python 3.7+
- [uv](https://github.com/astral-sh/uv) (recommended) or pip
- [opencode](https://opencode.ai/docs/) installed at `/opt/homebrew/bin/opencode`
- Trail API key with permission to update entries

## Usage

### Preview (Dry Run)

```bash
uv run rewrite_entry.py --api-key YOUR_API_KEY --entry-id ABC123 --dry-run
```

### Actually Update

```bash
uv run rewrite_entry.py --api-key YOUR_API_KEY --entry-id ABC123
```

### Using Environment Variable

```bash
export TRAIL_API_KEY=your_api_key
uv run rewrite_entry.py --entry-id ABC123
```

## Options

| Option | Required | Description |
|--------|----------|-------------|
| `--entry-id` | Yes | Hash ID of the entry to rewrite |
| `--api-key` | No* | Trail API token (*or set `TRAIL_API_KEY` env var) |
| `--api-url` | No | API base URL (default: https://trail.services.kibotu.net/api) |
| `--dry-run` | No | Preview rewritten text without updating |
| `--model` | No | Override opencode model (e.g., `anthropic/claude-sonnet-4.5`) |
| `-v, --verbose` | No | Show verbose output for debugging |

## Examples

```bash
# Quick preview
uv run rewrite_entry.py --api-key $KEY --entry-id 000N88NS --dry-run

# Use Claude Sonnet
uv run rewrite_entry.py --api-key $KEY --entry-id 000N88NS --model anthropic/claude-sonnet-4.5

# Verbose mode for debugging
uv run rewrite_entry.py --api-key $KEY --entry-id 000N88NS --dry-run -v
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

- URL preservation check: aborts if URL appears to be missing from rewritten text
- Dry-run mode for previewing changes
- No tools enabled for the AI agent (pure text transformation)

## Troubleshooting

### "opencode not found"

Install opencode: https://opencode.ai/docs/

### "Access denied (not your entry or not admin)"

You need to either own the entry or have admin privileges to update it.

### "Entry not found"

Check that the hash ID is correct. You can find it in the entry URL or via the API.
