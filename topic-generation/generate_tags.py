#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.7"
# dependencies = [
#     "requests>=2.31.0",
#     "urllib3>=2.0.0",
# ]
# ///
"""
Trail Topic Generation Script

This script generates AI-powered tags for Trail entries using opencode.

Features:
- Fetches all entries via cursor-based pagination
- Uses opencode CLI to generate tags based on entry metadata + URL content
- Applies tags via Trail API (PUT /api/entries/{hash_id}/tags)
- Supports resume via JSON cache file
- Dry-run mode for testing (first 5 entries, no API writes)
- Graceful shutdown with cache save on Ctrl+C

Usage:
    uv run generate_tags.py --api-key YOUR_API_KEY [--dry-run] [--limit N] [-v]
"""

import argparse
import json
import os
import re
import signal
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

# Constants
OPENCODE_BIN = "/opt/homebrew/bin/opencode"
CACHE_VERSION = 1
DEFAULT_API_URL = "https://trail.services.kibotu.net/api"
DEFAULT_DELAY_MS = 2000  # 2 seconds between opencode calls


def get_base_url_from_secrets() -> Optional[str]:
    """Try to read base_url from backend/secrets.yml."""
    try:
        import yaml
        possible_paths = [
            Path(__file__).parent.parent / "backend" / "secrets.yml",
            Path(__file__).parent / ".." / "backend" / "secrets.yml",
            Path("../backend/secrets.yml"),
        ]
        
        for secrets_path in possible_paths:
            if secrets_path.exists():
                try:
                    with open(secrets_path, "r") as f:
                        config = yaml.safe_load(f)
                        base_url = config.get("app", {}).get("base_url")
                        if base_url:
                            return f"{base_url}/api"
                except Exception:
                    pass
    except ImportError:
        pass  # yaml not available, skip
    return None


class TagGenerator:
    """Generate and apply AI-powered tags for Trail entries."""

    def __init__(
        self,
        api_key: str,
        api_url: Optional[str] = None,
        cache_file: str = ".tag_generation_cache.json",
        dry_run: bool = False,
        skip_tagged: bool = False,
        delay_ms: int = DEFAULT_DELAY_MS,
        model: Optional[str] = None,
        verbose: bool = False,
    ):
        self.api_key = api_key
        self.api_url = (
            api_url
            or os.environ.get("TRAIL_API_URL")
            or get_base_url_from_secrets()
            or DEFAULT_API_URL
        )
        self.cache_file = cache_file
        self.dry_run = dry_run
        self.skip_tagged = skip_tagged
        self.delay_ms = delay_ms
        self.model = model
        self.verbose = verbose

        # Statistics
        self.stats = {
            "total_entries": 0,
            "skipped_cached": 0,
            "skipped_has_tags": 0,
            "processed": 0,
            "failed": 0,
            "start_time": None,
            "end_time": None,
        }

        # Cache: hash_id -> list of tags
        self.processed: Dict[str, List[str]] = {}

        # Load existing cache
        self._load_cache()

        # Setup HTTP session with retry logic
        self.session = self._create_session()

    def _create_session(self) -> requests.Session:
        """Create HTTP session with retry logic."""
        session = requests.Session()
        retry = Retry(
            total=3,
            backoff_factor=1,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["GET", "PUT", "POST"],
        )
        adapter = HTTPAdapter(max_retries=retry)
        session.mount("http://", adapter)
        session.mount("https://", adapter)
        session.headers.update({
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        })
        return session

    def _load_cache(self) -> None:
        """Load cache from disk."""
        path = Path(self.cache_file)
        if not path.exists():
            self.processed = {}
            return

        try:
            with open(path, "r", encoding="utf-8") as f:
                data = json.load(f)
            if data.get("version") != CACHE_VERSION:
                print(f"⚠️  Cache version mismatch, starting fresh")
                self.processed = {}
                return
            self.processed = data.get("processed", {})
            print(f"📦 Loaded cache: {len(self.processed)} entries already processed")
        except (json.JSONDecodeError, KeyError) as e:
            print(f"⚠️  Warning: corrupt cache file, starting fresh ({e})")
            self.processed = {}

    def _save_cache(self) -> None:
        """Atomically write cache to disk."""
        path = Path(self.cache_file)
        tmp_path = path.with_suffix(".tmp")

        data = {
            "version": CACHE_VERSION,
            "processed": self.processed,
            "stats": {
                "total_processed": len(self.processed),
                "last_updated": datetime.now().isoformat(),
            },
        }

        with open(tmp_path, "w", encoding="utf-8") as f:
            json.dump(data, f, indent=2, ensure_ascii=False)

        # Atomic rename -- safe against Ctrl+C mid-write
        tmp_path.rename(path)

    def fetch_all_entries(self) -> List[Dict]:
        """Fetch all entries using cursor-based pagination."""
        entries = []
        cursor = None
        page = 0

        print("📥 Fetching entries from API...")

        while True:
            page += 1
            params = {"limit": 100}
            if cursor:
                params["before"] = cursor

            try:
                resp = self.session.get(
                    f"{self.api_url}/entries",
                    params=params,
                    timeout=30
                )
                resp.raise_for_status()
                data = resp.json()
            except requests.exceptions.RequestException as e:
                print(f"❌ Failed to fetch entries: {e}")
                raise

            batch = data.get("entries", [])
            entries.extend(batch)
            print(f"  Page {page}: fetched {len(batch)} entries ({len(entries)} total)")

            if not data.get("has_more", False):
                break
            cursor = data.get("next_cursor")

        # Process newest first (most recent entries are more relevant)
        entries.sort(key=lambda e: e.get("created_at", ""), reverse=True)
        return entries

    def _build_prompt(self, entry: Dict) -> str:
        """Build the opencode prompt string from entry metadata."""
        text = entry.get("text", "")
        url = entry.get("preview_url") or ""
        title = entry.get("preview_title") or ""
        description = entry.get("preview_description") or ""
        site = entry.get("preview_site_name") or ""

        parts = [
            "You are a content categorization expert.",
            "Generate up to 8 relevant tags for the following link entry.",
        ]

        if url:
            parts.append(f"First, fetch this URL to understand the content: {url}")

        parts.append("")
        parts.append(f'Entry text: "{text}"')
        if url:
            parts.append(f"URL: {url}")
        if title:
            parts.append(f"Title: {title}")
        if description:
            parts.append(f"Description: {description}")
        if site:
            parts.append(f"Site: {site}")

        parts.extend([
            "",
            "Rules:",
            "- Output ONLY a valid JSON array of lowercase kebab-case tag strings",
            "- Tags must be specific and useful for rediscovery",
            "  Good: 'machine-learning', 'python', 'gpt', 'react-hooks'",
            "  Bad: 'tech', 'interesting', 'cool', 'link'",
            "- Include topic tags (what it is about) and type tags (tutorial, tool, library, article, video, paper, repo)",
            "- Between 1 and 8 tags",
            "- No markdown, no explanation, no preamble -- just the JSON array",
            '- Example: ["python", "machine-learning", "transformer", "tutorial", "neural-networks"]',
        ])

        return "\n".join(parts)

    def _run_opencode(self, prompt: str) -> str:
        """Execute opencode run and return stdout."""
        cmd = [OPENCODE_BIN, "run"]

        if self.model:
            cmd.extend(["--model", self.model])

        cmd.append(prompt)

        try:
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=120,  # 2-minute timeout per entry
                cwd=str(Path(__file__).parent),
            )

            if result.returncode != 0:
                if self.verbose:
                    print(f"    stderr: {result.stderr[:200]}")
                raise RuntimeError(f"opencode exited with code {result.returncode}")

            return result.stdout

        except subprocess.TimeoutExpired:
            raise RuntimeError("opencode timed out after 120 seconds")

    def _parse_tags(self, output: str) -> List[str]:
        """Extract a JSON array of tag strings from opencode output."""
        # Strip ANSI escape codes
        clean = re.sub(r'\x1b\[[0-9;]*m', '', output)

        # Find all JSON arrays in the output -- take the last one
        # (opencode may echo the prompt or show intermediate thinking)
        pattern = r'\[(?:\s*"[a-z0-9][a-z0-9-]*"(?:\s*,\s*"[a-z0-9][a-z0-9-]*")*\s*)\]'
        matches = re.findall(pattern, clean)

        if not matches:
            # Fallback: try a more lenient match for any JSON array of strings
            lenient = r'\[\s*"[^"]+(?:"\s*,\s*"[^"]+)*"\s*\]'
            matches = re.findall(lenient, clean)

        if not matches:
            if self.verbose:
                print(f"    Raw output: {clean[:500]}")
            raise ValueError(f"No JSON tag array found in opencode output")

        # Take the last match (most likely the final answer)
        tags = json.loads(matches[-1])

        # Validate and normalize
        validated = []
        for tag in tags:
            if not isinstance(tag, str):
                continue
            # Normalize: lowercase, strip, replace spaces with hyphens
            tag = tag.lower().strip().replace(" ", "-")
            # Remove invalid characters (keep alphanumeric and hyphens)
            tag = re.sub(r'[^a-z0-9-]', '', tag)
            # Remove leading/trailing hyphens
            tag = tag.strip("-")
            if tag and len(tag) >= 2:
                validated.append(tag)

        if not validated:
            raise ValueError("No valid tags after normalization")

        # Cap at 8 tags
        return validated[:8]

    def _apply_tags(self, hash_id: str, tags: List[str]) -> bool:
        """Write tags to Trail API. Returns True on success."""
        try:
            resp = self.session.put(
                f"{self.api_url}/entries/{hash_id}/tags",
                json={"tags": tags},
                timeout=15,
            )
            resp.raise_for_status()
            return True
        except requests.exceptions.HTTPError as e:
            print(f"    ❌ Failed to apply tags: {e}")
            if e.response is not None:
                print(f"       Response: {e.response.text[:200]}")
            return False
        except requests.exceptions.RequestException as e:
            print(f"    ❌ Network error applying tags: {e}")
            return False

    def run(self, limit: Optional[int] = None) -> None:
        """Main loop: fetch, iterate, generate, apply, cache."""
        print("🏷️  Trail Tag Generator")
        print(f"   API:        {self.api_url}")
        print(f"   Cache:      {self.cache_file}")
        print(f"   Dry run:    {self.dry_run}")
        print(f"   Model:      {self.model or '(default)'}")
        print()

        # Step 1: Fetch all entries
        entries = self.fetch_all_entries()
        self.stats["total_entries"] = len(entries)
        print(f"✅ Total entries: {len(entries)}")
        print()

        # Step 2: Filter
        to_process = []
        for entry in entries:
            hid = entry.get("hash_id")
            if not hid:
                continue
            if hid in self.processed:
                self.stats["skipped_cached"] += 1
                continue
            if self.skip_tagged and entry.get("tags"):
                self.stats["skipped_has_tags"] += 1
                continue
            to_process.append(entry)

        # Dry run: cap at 5
        if self.dry_run:
            to_process = to_process[:5]
            print(f"🧪 Dry run: processing first {len(to_process)} entries only")

        # Limit
        if limit and not self.dry_run:
            to_process = to_process[:limit]

        print(f"📋 To process: {len(to_process)}")
        print(f"⏭️  Skipped (cached): {self.stats['skipped_cached']}")
        if self.skip_tagged:
            print(f"⏭️  Skipped (has tags): {self.stats['skipped_has_tags']}")
        print()

        # Step 3: Process
        self.stats["start_time"] = datetime.now()

        for idx, entry in enumerate(to_process, 1):
            hid = entry["hash_id"]
            text = entry.get("text", "")[:60]
            url = entry.get("preview_url") or "(no url)"

            print(f"[{idx}/{len(to_process)}] {hid} {text}...")

            try:
                # Generate
                prompt = self._build_prompt(entry)
                if self.verbose:
                    print(f"    Prompt length: {len(prompt)} chars")
                
                output = self._run_opencode(prompt)
                tags = self._parse_tags(output)
                print(f"    🏷️  Tags: {tags}")

                # Apply
                if not self.dry_run:
                    success = self._apply_tags(hid, tags)
                    if success:
                        print(f"    ✅ Applied to API")
                    else:
                        self.stats["failed"] += 1
                        continue
                else:
                    print(f"    [DRY RUN] Would apply tags to API")

                # Cache (skip in dry-run)
                if not self.dry_run:
                    self.processed[hid] = tags
                    self._save_cache()

                self.stats["processed"] += 1

            except Exception as e:
                print(f"    ❌ Error: {e}")
                self.stats["failed"] += 1

            # Delay between opencode invocations
            if idx < len(to_process):
                time.sleep(self.delay_ms / 1000.0)

        self.stats["end_time"] = datetime.now()
        self.print_summary()

    def print_summary(self) -> None:
        """Print import summary statistics."""
        if not self.stats["start_time"] or not self.stats["end_time"]:
            return

        duration = (self.stats["end_time"] - self.stats["start_time"]).total_seconds()
        
        print("\n" + "=" * 60)
        print("📊 SUMMARY")
        print("=" * 60)
        print(f"Total entries:              {self.stats['total_entries']}")
        print(f"Processed:                  {self.stats['processed']} ✅")
        print(f"Failed:                     {self.stats['failed']} ❌")
        print(f"Skipped (cached):           {self.stats['skipped_cached']} ⏭️")
        if self.skip_tagged:
            print(f"Skipped (has tags):         {self.stats['skipped_has_tags']} ⏭️")
        print("-" * 60)
        print(f"Duration:                   {duration:.1f} seconds")
        
        if self.stats['processed'] > 0:
            avg_time = duration / self.stats['processed']
            print(f"Average time per entry:     {avg_time:.1f} seconds")
        
        print("=" * 60)


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description="Generate AI-powered tags for Trail entries",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Quick test (dry run)
  uv run generate_tags.py --api-key YOUR_API_KEY --dry-run

  # Process all entries, skip ones with existing tags
  uv run generate_tags.py --api-key YOUR_API_KEY --skip-tagged

  # Use specific model
  uv run generate_tags.py --api-key YOUR_API_KEY --model anthropic/claude-sonnet-4.5

  # Resume after interruption (automatic)
  uv run generate_tags.py --api-key YOUR_API_KEY

  # Verbose mode for debugging
  uv run generate_tags.py --api-key YOUR_API_KEY --dry-run -v
        """,
    )
    
    parser.add_argument(
        "--api-key",
        help="API key for Trail API authentication (or set TRAIL_API_KEY env var)",
    )
    
    default_api_url = (
        os.environ.get("TRAIL_API_URL")
        or get_base_url_from_secrets()
        or DEFAULT_API_URL
    )
    
    parser.add_argument(
        "--api-url",
        default=default_api_url,
        help=f"Trail API base URL (default: {default_api_url})",
    )
    
    parser.add_argument(
        "--cache-file",
        default=".tag_generation_cache.json",
        help="Path to cache file for resume support (default: .tag_generation_cache.json)",
    )
    
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Process first 5 entries only, print tags but don't write to API",
    )
    
    parser.add_argument(
        "--skip-tagged",
        action="store_true",
        help="Skip entries that already have tags assigned",
    )
    
    parser.add_argument(
        "--delay-ms",
        type=int,
        default=DEFAULT_DELAY_MS,
        help=f"Delay between opencode invocations in milliseconds (default: {DEFAULT_DELAY_MS})",
    )
    
    parser.add_argument(
        "--limit",
        type=int,
        help="Limit number of entries to process (for testing)",
    )
    
    parser.add_argument(
        "--model",
        help="Override opencode model (e.g. anthropic/claude-sonnet-4.5)",
    )
    
    parser.add_argument(
        "-v",
        "--verbose",
        action="store_true",
        help="Enable verbose mode (show full opencode output)",
    )

    args = parser.parse_args()

    # Get API key from args or environment
    api_key = args.api_key or os.environ.get("TRAIL_API_KEY")
    if not api_key:
        print("❌ Error: API key required. Provide via --api-key or TRAIL_API_KEY env var")
        sys.exit(1)

    # Validate opencode is installed
    if not Path(OPENCODE_BIN).exists():
        print(f"❌ Error: opencode not found at {OPENCODE_BIN}")
        print("   Install opencode: https://opencode.ai/docs/")
        sys.exit(1)

    # Create generator
    generator = TagGenerator(
        api_key=api_key,
        api_url=args.api_url,
        cache_file=args.cache_file,
        dry_run=args.dry_run,
        skip_tagged=args.skip_tagged,
        delay_ms=args.delay_ms,
        model=args.model,
        verbose=args.verbose,
    )

    # Setup signal handlers for graceful shutdown
    def handle_signal(signum, frame):
        print("\n\n⚠️  Interrupted -- saving cache and exiting...")
        if not generator.dry_run:
            generator._save_cache()
        generator.print_summary()
        sys.exit(1)

    signal.signal(signal.SIGINT, handle_signal)
    signal.signal(signal.SIGTERM, handle_signal)

    # Run
    try:
        generator.run(limit=args.limit)
    except KeyboardInterrupt:
        print("\n\n⚠️  Interrupted by user")
        if not generator.dry_run:
            generator._save_cache()
        generator.print_summary()
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ Fatal error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    main()
