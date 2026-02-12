# Trail Topic Generation

AI-powered tag generation for Trail entries. Automatically categorize your 3,000+ saved links with meaningful, discoverable tags.

## Quick Start

```bash
# Test with first 5 entries (dry run)
uv run generate_tags.py --api-key YOUR_API_KEY --dry-run

# Process all entries
uv run generate_tags.py --api-key YOUR_API_KEY

# Resume after interruption (automatic)
uv run generate_tags.py --api-key YOUR_API_KEY
```

## Prerequisites

1. **API Key** from [trail.services.kibotu.net](https://trail.services.kibotu.net) (Profile → API Token)
2. **opencode CLI** installed and authenticated (`opencode auth login`)
3. **Python 3.7+** with `uv` (auto-installs dependencies)

## Installation

```bash
# Install uv (if not already installed)
curl -LsSf https://astral.sh/uv/install.sh | sh

# Install opencode (if not already installed)
curl -fsSL https://opencode.ai/install.sh | sh

# Authenticate opencode with your LLM provider
opencode auth login
```

## Usage

### Recommended (secure)

```bash
export TRAIL_API_KEY="your_api_key"
uv run generate_tags.py
```

### Test First

```bash
# Dry run: process 5 entries, print tags, no API writes
uv run generate_tags.py --api-key KEY --dry-run

# Verbose mode to see full opencode output
uv run generate_tags.py --api-key KEY --dry-run -v
```

### Full Run

```bash
# Process all entries, skip ones that already have tags
uv run generate_tags.py --api-key KEY --skip-tagged

# Use a specific model for higher quality
uv run generate_tags.py --api-key KEY --model anthropic/claude-sonnet-4.5

# Limit to first N entries (processes newest first)
uv run generate_tags.py --api-key KEY --limit 10
```

### Resume After Interruption

The script automatically saves progress to `.tag_generation_cache.json`. Just re-run the same command:

```bash
uv run generate_tags.py --api-key KEY
```

## Options

| Argument | Default | Description |
|----------|---------|-------------|
| `--api-key` | *(required)* | Bearer token for Trail API (or set `TRAIL_API_KEY` env var) |
| `--api-url` | `https://trail.services.kibotu.net/api` | API base URL |
| `--cache-file` | `.tag_generation_cache.json` | Path to resume cache |
| `--dry-run` | `false` | Process only first 5 entries; print tags but don't write to API |
| `--skip-tagged` | `false` | Skip entries that already have tags |
| `--delay-ms` | `2000` | Delay between opencode invocations (milliseconds) |
| `--limit` | *(none)* | Cap number of entries to process |
| `--model` | *(default)* | Override opencode model (e.g. `anthropic/claude-haiku-3.5`) |
| `-v, --verbose` | `false` | Print full opencode output |

## How It Works

1. **Fetch** all entries via cursor-based pagination (GET `/api/entries`)
2. **Sort** entries newest-first (most recent content is more relevant)
3. **Skip** entries already in cache or with existing tags (if `--skip-tagged`)
4. **Generate** tags for each entry:
   - Build prompt with entry text, URL, title, description
   - Run `opencode run` to generate tags (AI fetches the URL for context)
   - Parse JSON array of tags from opencode output
5. **Apply** tags via API (PUT `/api/entries/{hash_id}/tags`)
6. **Cache** progress after each entry (resume-safe on Ctrl+C)

## Performance

- **~3,000 entries** at ~20 seconds per entry = **15-25 hours**
- Use `tmux` or `screen` for long-running sessions
- Resume support means you can run in chunks over multiple sessions
- Consider `--model anthropic/claude-haiku-3.5` for faster (cheaper) generation

## Tag Quality

The AI generates:
- **Topic tags**: what the content is about (e.g. `machine-learning`, `python`, `gpt`)
- **Type tags**: content format (e.g. `tutorial`, `tool`, `library`, `article`, `video`)
- **Specific tags**: useful for rediscovery (e.g. `react-hooks`, not just `react`)

Tags are:
- Lowercase kebab-case (e.g. `machine-learning`)
- Between 1-8 tags per entry
- Normalized and validated before API write

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "opencode not found" | Install: `curl -fsSL https://opencode.ai/install.sh \| sh` |
| "401 Unauthorized" | Get new API key from trail.services.kibotu.net |
| "opencode timed out" | Normal for slow sites; entry is skipped and can be retried |
| "No JSON array found" | opencode output parsing failed; check with `-v` flag |
| Start fresh | `rm .tag_generation_cache.json` |

## Security

```bash
# Preferred: use environment variable
export TRAIL_API_KEY="your_api_key"
uv run generate_tags.py

# Never commit tokens
# Already in .gitignore
```

## Cache Structure

`.tag_generation_cache.json`:

```json
{
  "version": 1,
  "processed": {
    "000N88NS": ["python", "gpt", "machine-learning", "tutorial"],
    "ABC12345": ["rust", "systems-programming", "performance"]
  },
  "stats": {
    "total_processed": 2,
    "last_updated": "2026-02-12T14:30:00"
  }
}
```

## Links

- Trail API: https://trail.services.kibotu.net/api
- opencode: https://opencode.ai/docs/
- uv: https://docs.astral.sh/uv/
