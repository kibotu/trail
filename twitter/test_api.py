#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.7"
# dependencies = [
#     "requests>=2.31.0",
# ]
# ///
"""
Test script to validate Trail API functionality before full import.

Tests:
1. Single tweet without media
2. Single tweet with one image
3. Single tweet with multiple images (if supported)
4. Custom timestamp
5. Initial claps
"""

import argparse
import base64
import json
import sys
from pathlib import Path

import requests


def test_basic_entry(api_key: str, api_url: str):
    """Test creating a basic entry without media."""
    print("\n1️⃣ Testing basic entry creation...")
    
    payload = {
        "text": "Test tweet from import script",
        "created_at": "Fri Jan 30 12:00:00 +0000 2026",
    }
    
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
    }
    
    try:
        response = requests.post(
            f"{api_url}/entries",
            json=payload,
            headers=headers,
            timeout=10,
        )
        response.raise_for_status()
        result = response.json()
        print(f"   ✅ Success! Entry ID: {result.get('id')}")
        return result
    except Exception as e:
        print(f"   ❌ Failed: {e}")
        return None


def test_entry_with_image(api_key: str, api_url: str, image_path: Path):
    """Test creating an entry with one image."""
    print("\n2️⃣ Testing entry with single image...")
    
    if not image_path.exists():
        print(f"   ⚠️  Image not found: {image_path}")
        return None
    
    # Read and encode image
    with open(image_path, "rb") as f:
        image_data = base64.b64encode(f.read()).decode("utf-8")
    
    payload = {
        "text": "Test tweet with image",
        "created_at": "Fri Jan 30 12:01:00 +0000 2026",
        "media": [{
            "data": image_data,
            "filename": image_path.name,
            "mime_type": "image/jpeg",
            "image_type": "post",
        }],
        "raw_upload": True,
    }
    
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
    }
    
    try:
        response = requests.post(
            f"{api_url}/entries",
            json=payload,
            headers=headers,
            timeout=30,
        )
        response.raise_for_status()
        result = response.json()
        print(f"   ✅ Success! Entry ID: {result.get('id')}")
        print(f"   📷 Images: {len(result.get('images', []))}")
        return result
    except Exception as e:
        print(f"   ❌ Failed: {e}")
        if hasattr(e, 'response') and e.response is not None:
            print(f"   Response: {e.response.text[:200]}")
        return None


def test_entry_with_multiple_images(api_key: str, api_url: str, image_paths: list):
    """Test creating an entry with multiple images."""
    print("\n3️⃣ Testing entry with multiple images...")
    
    available_images = [p for p in image_paths if p.exists()]
    if len(available_images) < 2:
        print(f"   ⚠️  Need at least 2 images, found {len(available_images)}")
        return None
    
    # Use first 2 images
    images = available_images[:2]
    
    media = []
    for img_path in images:
        with open(img_path, "rb") as f:
            image_data = base64.b64encode(f.read()).decode("utf-8")
        media.append({
            "data": image_data,
            "filename": img_path.name,
            "mime_type": "image/jpeg",
            "image_type": "post",
        })
    
    payload = {
        "text": "Test tweet with multiple images",
        "created_at": "Fri Jan 30 12:02:00 +0000 2026",
        "media": media,
        "raw_upload": True,
    }
    
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
    }
    
    try:
        response = requests.post(
            f"{api_url}/entries",
            json=payload,
            headers=headers,
            timeout=30,
        )
        response.raise_for_status()
        result = response.json()
        print(f"   ✅ Success! Entry ID: {result.get('id')}")
        print(f"   📷 Images uploaded: {len(media)}")
        print(f"   📷 Images in response: {len(result.get('images', []))}")
        return result
    except Exception as e:
        print(f"   ❌ Failed: {e}")
        if hasattr(e, 'response') and e.response is not None:
            print(f"   Response: {e.response.text[:200]}")
        return None


def test_entry_with_claps(api_key: str, api_url: str):
    """Test creating an entry with initial claps."""
    print("\n4️⃣ Testing entry with initial claps...")
    
    payload = {
        "text": "Test tweet with engagement metrics",
        "created_at": "Fri Jan 30 12:03:00 +0000 2026",
        "initial_claps": 25,
    }
    
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
    }
    
    try:
        response = requests.post(
            f"{api_url}/entries",
            json=payload,
            headers=headers,
            timeout=10,
        )
        response.raise_for_status()
        result = response.json()
        print(f"   ✅ Success! Entry ID: {result.get('id')}")
        print(f"   👏 Claps: {result.get('clap_count', 0)}")
        return result
    except Exception as e:
        print(f"   ❌ Failed: {e}")
        return None


def main():
    parser = argparse.ArgumentParser(description="Test Trail API functionality")
    parser.add_argument("--api-key", required=True, help="API key")
    parser.add_argument(
        "--api-url",
        default="https://trail.services.kibotu.net/api",
        help="API base URL",
    )
    parser.add_argument(
        "--archive",
        default="./twitter-2026-01-30-b4863867977f12d90ca44e22411e7687a38ad392aa6188c046556e34064009a6",
        help="Path to Twitter archive (to find test images)",
    )
    
    args = parser.parse_args()
    
    print("🧪 Trail API Test Suite")
    print(f"🌐 API: {args.api_url}")
    print("=" * 60)
    
    # Find test images from archive
    archive_path = Path(args.archive)
    media_folder = archive_path / "data" / "tweets_media"
    
    test_images = []
    if media_folder.exists():
        for img_path in media_folder.iterdir():
            if img_path.suffix.lower() in [".jpg", ".jpeg", ".png"]:
                test_images.append(img_path)
                if len(test_images) >= 3:
                    break
    
    # Run tests
    results = {
        "basic": test_basic_entry(args.api_key, args.api_url),
        "with_claps": test_entry_with_claps(args.api_key, args.api_url),
    }
    
    if test_images:
        results["single_image"] = test_entry_with_image(
            args.api_key, args.api_url, test_images[0]
        )
        
        if len(test_images) >= 2:
            results["multiple_images"] = test_entry_with_multiple_images(
                args.api_key, args.api_url, test_images
            )
    else:
        print("\n⚠️  No test images found in archive")
    
    # Summary
    print("\n" + "=" * 60)
    print("📊 TEST SUMMARY")
    print("=" * 60)
    
    passed = sum(1 for r in results.values() if r is not None)
    total = len(results)
    
    for test_name, result in results.items():
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{test_name:20s} {status}")
    
    print("=" * 60)
    print(f"Result: {passed}/{total} tests passed")
    
    if passed == total:
        print("\n✅ All tests passed! Ready for full import.")
        sys.exit(0)
    else:
        print("\n⚠️  Some tests failed. Review errors above.")
        sys.exit(1)


if __name__ == "__main__":
    main()
