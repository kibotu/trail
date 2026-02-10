#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.7"
# dependencies = [
#     "requests>=2.31.0",
#     "urllib3>=2.0.0",
# ]
# ///
"""
Twitter Archive to Trail API Import Script

This script imports a Twitter archive (tweets + media) to the Trail API.

Features:
- Parses Twitter archive JSON data
- Imports both original tweets AND retweets
- Converts images to base64 for inline upload
- Preserves original timestamps
- Maps Twitter favorites/likes to Trail claps (up to 100,000 with raw_upload)
- Supports rate limiting and retry logic
- Maintains Twitter ID → Trail ID mapping
- Excludes videos (MP4 files)

Note on View Counts:
    Twitter's data archive does NOT include view/impression counts for tweets.
    View counts are classified as "non-public metrics" by Twitter and are only
    available through the Twitter Developer API with OAuth authentication,
    with a 30-day restriction. If you need to import view counts, you would
    need to fetch them separately via the Twitter API before the data expires.

Usage:
    uv run import_twitter_archive.py --jwt YOUR_JWT_TOKEN [--dry-run] [--limit N] [-v]
"""

import argparse
import base64
import json
import os
import re
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry


class TwitterArchiveImporter:
    """Import Twitter archive to Trail API."""

    def __init__(
        self,
        archive_path: str,
        jwt_token: str,
        api_base_url: str = "https://trail.services.kibotu.net/api",
        delay_ms: int = 100,
        dry_run: bool = False,
        verbose: bool = False,
        skip_ids: Optional[List[str]] = None,
        cache_file: Optional[str] = None,
    ):
        self.archive_path = Path(archive_path)
        self.jwt_token = jwt_token
        self.api_base_url = api_base_url
        self.delay_ms = delay_ms
        self.dry_run = dry_run
        self.verbose = verbose
        self.skip_ids = set(skip_ids) if skip_ids else set()
        self.cache_file = cache_file

        # Statistics
        self.stats = {
            "total_tweets": 0,
            "tweets_with_media": 0,
            "tweets_imported": 0,
            "tweets_failed": 0,
            "tweets_skipped": 0,
            "retweets_imported": 0,
            "original_tweets_imported": 0,
            "media_files_processed": 0,
            "media_files_skipped": 0,
            "total_claps_imported": 0,
            "start_time": None,
            "end_time": None,
        }

        # Mapping: Twitter ID → Trail ID
        self.id_mapping: Dict[str, int] = {}

        # Load existing cache if provided
        if self.cache_file:
            self._load_cache()

        # Setup HTTP session with retry logic
        self.session = self._create_session()

    def _load_cache(self):
        """Load existing cache file to resume migration."""
        if not self.cache_file or not Path(self.cache_file).exists():
            return
        
        try:
            with open(self.cache_file, "r", encoding="utf-8") as f:
                cache_data = json.load(f)
            
            # Load existing mappings
            migrated_tweets = cache_data.get("migrated_tweets", {})
            self.id_mapping.update(migrated_tweets)
            
            # Add to skip list
            self.skip_ids.update(migrated_tweets.keys())
            
            print(f"📦 Loaded cache: {len(migrated_tweets)} previously migrated tweets")
        except Exception as e:
            print(f"⚠️  Warning: Could not load cache file: {e}")

    def _create_session(self) -> requests.Session:
        """Create HTTP session with retry logic."""
        session = requests.Session()
        retry = Retry(
            total=3,
            backoff_factor=1,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["POST", "GET"],
        )
        adapter = HTTPAdapter(max_retries=retry)
        session.mount("http://", adapter)
        session.mount("https://", adapter)
        session.headers.update({
            "Authorization": f"Bearer {self.jwt_token}",
            "Content-Type": "application/json",
        })
        return session

    def parse_tweets_js(self) -> List[Dict]:
        """Parse tweets.js file and extract tweet data."""
        tweets_file = self.archive_path / "data" / "tweets.js"
        
        if not tweets_file.exists():
            raise FileNotFoundError(f"Tweets file not found: {tweets_file}")

        print(f"📖 Reading tweets from: {tweets_file}")
        
        with open(tweets_file, "r", encoding="utf-8") as f:
            content = f.read()

        # Remove JavaScript wrapper: window.YTD.tweets.part0 = 
        json_str = re.sub(r"^window\.YTD\.tweets\.part0\s*=\s*", "", content)
        
        tweets_data = json.loads(json_str)
        print(f"✅ Parsed {len(tweets_data)} tweets")
        
        return tweets_data

    def get_media_files(self) -> Dict[str, List[Path]]:
        """
        Scan tweets_media folder and map files to tweet IDs.
        
        Filename pattern: {tweet_id}-{media_id}.{ext}
        Returns: {tweet_id: [file_path1, file_path2, ...]}
        """
        media_folder = self.archive_path / "data" / "tweets_media"
        
        if not media_folder.exists():
            print("⚠️  No tweets_media folder found")
            return {}

        media_map: Dict[str, List[Path]] = {}
        
        for file_path in media_folder.iterdir():
            if not file_path.is_file():
                continue

            # Skip videos (MP4)
            if file_path.suffix.lower() in [".mp4", ".mov", ".webm"]:
                self.stats["media_files_skipped"] += 1
                continue

            # Extract tweet ID from filename: {tweet_id}-{media_id}.{ext}
            match = re.match(r"(\d+)-", file_path.name)
            if match:
                tweet_id = match.group(1)
                if tweet_id not in media_map:
                    media_map[tweet_id] = []
                media_map[tweet_id].append(file_path)
                self.stats["media_files_processed"] += 1

        print(f"📁 Found {self.stats['media_files_processed']} image files")
        print(f"⏭️  Skipped {self.stats['media_files_skipped']} video files")
        
        return media_map

    def image_to_base64(self, file_path: Path) -> Tuple[str, str]:
        """
        Convert image file to base64 string.
        
        Returns: (base64_data, mime_type)
        """
        with open(file_path, "rb") as f:
            image_data = f.read()

        base64_data = base64.b64encode(image_data).decode("utf-8")
        
        # Determine MIME type from extension
        ext = file_path.suffix.lower()
        mime_types = {
            ".jpg": "image/jpeg",
            ".jpeg": "image/jpeg",
            ".png": "image/png",
            ".gif": "image/gif",
            ".webp": "image/webp",
            ".svg": "image/svg+xml",
        }
        mime_type = mime_types.get(ext, "image/jpeg")
        
        return base64_data, mime_type

    def convert_twitter_timestamp(self, twitter_date: str) -> str:
        """
        Twitter timestamp is already in the correct format.
        Format: "Fri Nov 28 10:54:34 +0000 2025"
        """
        return twitter_date

    def is_retweet(self, tweet: Dict) -> bool:
        """
        Check if a tweet is a retweet.
        
        Retweets in Twitter archive have full_text starting with "RT @".
        """
        full_text = tweet.get("full_text", "")
        return full_text.startswith("RT @")

    def map_favorites_to_claps(self, favorite_count: int) -> Optional[int]:
        """
        Map Twitter favorite_count to Trail initial_claps.
        
        Range: 1-100,000 (with raw_upload admin mode)
        Returns None if favorite_count is 0 (don't set claps)
        """
        if favorite_count == 0:
            return None
        # With raw_upload: true, we can use up to 100,000 claps
        return min(favorite_count, 100000)

    def map_views_to_initial_views(self, view_count: int) -> Optional[int]:
        """
        Map Twitter view_count to Trail initial_views.
        
        Note: Twitter's data archive does NOT include view counts.
        This method is kept for potential future use if view data is
        obtained separately (e.g., via Twitter API before 30-day expiry).
        
        Requires raw_upload admin mode.
        Returns None if view_count is 0 or not available.
        """
        if view_count <= 0:
            return None
        return view_count

    def prepare_entry_payload(
        self,
        tweet_data: Dict,
        media_files: Optional[List[Path]] = None,
    ) -> Dict:
        """
        Prepare API payload for creating an entry.
        
        Args:
            tweet_data: Tweet object from Twitter archive
            media_files: List of media file paths to attach
            
        Returns:
            API payload dict
        """
        tweet = tweet_data["tweet"]
        
        payload = {
            "text": tweet["full_text"],
            "created_at": self.convert_twitter_timestamp(tweet["created_at"]),
            "raw_upload": True,  # Skip image processing for faster imports
        }

        # Add engagement metrics (likes → claps)
        favorite_count = int(tweet.get("favorite_count", 0))
        initial_claps = self.map_favorites_to_claps(favorite_count)
        if initial_claps is not None:
            payload["initial_claps"] = initial_claps

        # Note: Twitter's data archive does NOT include view/impression counts.
        # View counts are classified as "non-public metrics" by Twitter and are
        # only available through the Twitter Developer API (with 30-day limit).
        # If you have view data from another source, you can add it here:
        #
        # view_count = int(tweet.get("view_count", 0))
        # initial_views = self.map_views_to_initial_views(view_count)
        # if initial_views is not None:
        #     payload["initial_views"] = initial_views

        # Add media if present
        if media_files:
            payload["media"] = []
            for media_file in media_files:
                base64_data, mime_type = self.image_to_base64(media_file)
                payload["media"].append({
                    "data": base64_data,
                    "filename": media_file.name,
                    "mime_type": mime_type,
                    "image_type": "post",
                })

        return payload

    def generate_curl_command(self, payload: Dict) -> str:
        """
        Generate a curl command equivalent for the API call.
        
        Args:
            payload: The JSON payload being sent
            
        Returns:
            Formatted curl command string
        """
        # Truncate base64 data in media for readability
        display_payload = payload.copy()
        if "media" in display_payload:
            display_payload["media"] = []
            for media_item in payload["media"]:
                truncated_media = media_item.copy()
                if "data" in truncated_media:
                    data_len = len(truncated_media["data"])
                    truncated_media["data"] = f"<BASE64_DATA_{data_len}_BYTES>"
                display_payload["media"].append(truncated_media)
        
        # Format JSON with proper escaping
        json_str = json.dumps(display_payload, ensure_ascii=False)
        
        curl_cmd = f"""curl -X POST \\
     -H "Authorization: Bearer <JWT_TOKEN>" \\
     -H "Content-Type: application/json" \\
     -d '{json_str}' \\
     {self.api_base_url}/entries"""
        
        return curl_cmd

    def create_entry(self, payload: Dict) -> Optional[Dict]:
        """
        Create an entry via Trail API.
        
        Returns:
            API response dict or None on failure
        """
        # Log curl equivalent if verbose mode is enabled
        if self.verbose:
            print("\n" + "─" * 60)
            print("📋 Curl equivalent:")
            print(self.generate_curl_command(payload))
            print("─" * 60 + "\n")
        
        if self.dry_run:
            print(f"  [DRY RUN] Would create entry: {payload['text'][:50]}...")
            return {"id": -1, "dry_run": True}

        try:
            response = self.session.post(
                f"{self.api_base_url}/entries",
                json=payload,
                timeout=30,
            )
            response.raise_for_status()
            return response.json()
        
        except requests.exceptions.HTTPError as e:
            print(f"  ❌ HTTP Error: {e}")
            if e.response is not None:
                print(f"     Response: {e.response.text[:200]}")
            return None
        
        except requests.exceptions.RequestException as e:
            print(f"  ❌ Request Error: {e}")
            return None

    def import_tweets(
        self,
        tweets_data: List[Dict],
        media_map: Dict[str, List[Path]],
        limit: Optional[int] = None,
    ):
        """
        Import tweets to Trail API.
        
        Args:
            tweets_data: List of tweet objects
            media_map: Mapping of tweet_id to media files
            limit: Optional limit on number of tweets to import
        """
        self.stats["total_tweets"] = len(tweets_data)
        self.stats["start_time"] = datetime.now()

        # Sort tweets chronologically (oldest first)
        tweets_data.sort(key=lambda x: x["tweet"]["created_at"])

        # Apply limit if specified
        if limit:
            tweets_data = tweets_data[:limit]
            print(f"⚠️  Limiting import to {limit} tweets")

        print(f"\n🚀 Starting import of {len(tweets_data)} tweets...")
        print(f"   Delay between requests: {self.delay_ms}ms")
        print(f"   Dry run: {self.dry_run}")
        print(f"   Verbose: {self.verbose}")
        print()

        for idx, tweet_data in enumerate(tweets_data, 1):
            tweet = tweet_data["tweet"]
            tweet_id = tweet["id_str"]
            text = tweet["full_text"]
            
            # Skip if already migrated
            if tweet_id in self.skip_ids:
                self.stats["tweets_skipped"] += 1
                continue
            
            # Get media files for this tweet
            media_files = media_map.get(tweet_id, [])
            
            if media_files:
                self.stats["tweets_with_media"] += 1

            # Prepare payload
            try:
                payload = self.prepare_entry_payload(tweet_data, media_files)
            except Exception as e:
                print(f"[{idx}/{len(tweets_data)}] ❌ Failed to prepare payload for tweet {tweet_id}")
                print(f"  Error: {e}")
                self.stats["tweets_failed"] += 1
                continue

            # Check if this is a retweet
            is_rt = self.is_retweet(tweet)

            # Display progress
            media_indicator = f"📷×{len(media_files)}" if media_files else ""
            rt_indicator = "🔁" if is_rt else ""
            print(f"[{idx}/{len(tweets_data)}] {rt_indicator}{media_indicator} {text[:60]}...")

            # Create entry
            result = self.create_entry(payload)
            
            if result:
                trail_id = result.get("id")
                self.id_mapping[tweet_id] = trail_id
                self.stats["tweets_imported"] += 1
                
                # Track retweets vs original tweets
                if is_rt:
                    self.stats["retweets_imported"] += 1
                else:
                    self.stats["original_tweets_imported"] += 1
                
                # Track engagement stats
                if "initial_claps" in payload:
                    self.stats["total_claps_imported"] += payload["initial_claps"]
                
                print(f"  ✅ Created entry ID: {trail_id}")
            else:
                self.stats["tweets_failed"] += 1
                print(f"  ❌ Failed to create entry")

            # Rate limiting delay
            if not self.dry_run and idx < len(tweets_data):
                time.sleep(self.delay_ms / 1000.0)

        self.stats["end_time"] = datetime.now()

    def save_id_mapping(self, output_file: str = "twitter_trail_id_mapping.json"):
        """Save Twitter ID → Trail ID mapping to file."""
        # Use cache file if specified, otherwise use default location
        if self.cache_file:
            output_path = Path(self.cache_file)
            
            # Load existing cache data
            cache_data = {}
            if output_path.exists():
                try:
                    with open(output_path, "r", encoding="utf-8") as f:
                        cache_data = json.load(f)
                except Exception:
                    pass
            
            # Update cache with new mappings
            if "migrated_tweets" not in cache_data:
                cache_data["migrated_tweets"] = {}
            cache_data["migrated_tweets"].update(self.id_mapping)
            
            # Update stats
            cache_data["stats"] = {
                "total_tweets": self.stats["total_tweets"],
                "migrated": len(cache_data["migrated_tweets"]),
                "failed": self.stats["tweets_failed"],
            }
            cache_data["last_updated"] = datetime.now().isoformat()
            
            with open(output_path, "w", encoding="utf-8") as f:
                json.dump(cache_data, f, indent=2)
        else:
            output_path = self.archive_path / output_file
            with open(output_path, "w", encoding="utf-8") as f:
                json.dump(self.id_mapping, f, indent=2)
        
        print(f"\n💾 Saved ID mapping to: {output_path}")

    def print_summary(self):
        """Print import summary statistics."""
        duration = (self.stats["end_time"] - self.stats["start_time"]).total_seconds()
        
        print("\n" + "=" * 60)
        print("📊 IMPORT SUMMARY")
        print("=" * 60)
        print(f"Total tweets in archive:    {self.stats['total_tweets']}")
        print(f"Tweets imported:            {self.stats['tweets_imported']} ✅")
        print(f"  - Original tweets:        {self.stats['original_tweets_imported']}")
        print(f"  - Retweets:               {self.stats['retweets_imported']} 🔁")
        print(f"Tweets skipped (cached):    {self.stats['tweets_skipped']} ⏭️")
        print(f"Tweets failed:              {self.stats['tweets_failed']} ❌")
        print(f"Tweets with media:          {self.stats['tweets_with_media']}")
        print(f"Media files processed:      {self.stats['media_files_processed']}")
        print(f"Media files skipped (video): {self.stats['media_files_skipped']}")
        print("-" * 60)
        print(f"Total claps imported:       {self.stats['total_claps_imported']:,} 👏")
        print("-" * 60)
        print(f"Duration:                   {duration:.1f} seconds")
        
        if self.stats['tweets_imported'] > 0:
            avg_time = duration / self.stats['tweets_imported']
            print(f"Average time per tweet:     {avg_time:.2f} seconds")
        
        print("=" * 60)

    def run(self, limit: Optional[int] = None):
        """Run the complete import process."""
        print("🐦 Twitter Archive to Trail API Importer")
        print(f"📂 Archive path: {self.archive_path}")
        print(f"🌐 API endpoint: {self.api_base_url}")
        print()

        # Step 1: Parse tweets
        tweets_data = self.parse_tweets_js()

        # Step 2: Get media files
        media_map = self.get_media_files()

        # Step 3: Import tweets
        self.import_tweets(tweets_data, media_map, limit)

        # Step 4: Save ID mapping
        if not self.dry_run:
            self.save_id_mapping()

        # Step 5: Print summary
        self.print_summary()


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description="Import Twitter archive to Trail API",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Dry run (test without importing)
  python import_twitter_archive.py --jwt YOUR_TOKEN --dry-run

  # Import first 10 tweets (testing)
  python import_twitter_archive.py --jwt YOUR_TOKEN --limit 10

  # Full import with verbose logging
  python import_twitter_archive.py --jwt YOUR_TOKEN -v

  # Verbose mode with limit (see curl equivalents)
  python import_twitter_archive.py --jwt YOUR_TOKEN --limit 5 -v

  # Custom archive path
  python import_twitter_archive.py --jwt YOUR_TOKEN --archive /path/to/archive
        """,
    )
    
    parser.add_argument(
        "--jwt",
        required=True,
        help="JWT authentication token for Trail API",
    )
    
    parser.add_argument(
        "--archive",
        default="./twitter-2026-01-30-b4863867977f12d90ca44e22411e7687a38ad392aa6188c046556e34064009a6",
        help="Path to Twitter archive folder (default: auto-detect)",
    )
    
    parser.add_argument(
        "--api-url",
        default="https://trail.services.kibotu.net/api",
        help="Trail API base URL (default: https://trail.services.kibotu.net/api)",
    )
    
    parser.add_argument(
        "--delay",
        type=int,
        default=100,
        help="Delay between requests in milliseconds (default: 100)",
    )
    
    parser.add_argument(
        "--limit",
        type=int,
        help="Limit number of tweets to import (for testing)",
    )
    
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Test run without actually creating entries",
    )
    
    parser.add_argument(
        "-v",
        "--verbose",
        action="store_true",
        help="Enable verbose mode (log curl equivalents of API calls)",
    )
    
    parser.add_argument(
        "--skip-ids",
        help="Comma-separated list of Twitter IDs to skip (already migrated)",
    )
    
    parser.add_argument(
        "--cache-file",
        help="Path to cache file for storing migration progress",
    )

    args = parser.parse_args()

    # Validate archive path
    archive_path = Path(args.archive)
    if not archive_path.exists():
        print(f"❌ Error: Archive path not found: {archive_path}")
        sys.exit(1)

    # Parse skip IDs if provided
    skip_ids = None
    if args.skip_ids:
        skip_ids = [id.strip() for id in args.skip_ids.split(",") if id.strip()]

    # Create importer and run
    importer = TwitterArchiveImporter(
        archive_path=str(archive_path),
        jwt_token=args.jwt,
        api_base_url=args.api_url,
        delay_ms=args.delay,
        dry_run=args.dry_run,
        verbose=args.verbose,
        skip_ids=skip_ids,
        cache_file=args.cache_file,
    )

    try:
        importer.run(limit=args.limit)
    except KeyboardInterrupt:
        print("\n\n⚠️  Import interrupted by user")
        importer.print_summary()
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ Fatal error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    main()
