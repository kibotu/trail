#!/usr/bin/env python3
"""
Generate Google Play Store listing assets for the Trail app.

Runs a Maestro flow to capture app screenshots, then processes them into:
  - Feature graphic (1024x500 PNG)
  - Phone screenshots (1080x1920, 9:16 PNG)
  - 7-inch tablet screenshots (1200x1920 PNG)
  - 10-inch tablet screenshots (1600x2560, 9:16 PNG)

Requirements:
  - Maestro CLI installed (`brew install maestro`)
  - Connected Android device or running emulator
  - Pillow (`pip install Pillow`)

Usage:
  python3 generate_playstore_assets.py [--device-id <id>] [--skip-capture]
"""

import argparse
import os
import random
import shutil
import subprocess
import sys
import time
from pathlib import Path

try:
    from PIL import Image, ImageDraw, ImageFont
except ImportError:
    print("Error: Pillow is required. Install with: pip install Pillow")
    sys.exit(1)

SCRIPT_DIR = Path(__file__).parent.resolve()
FLOW_FILE = SCRIPT_DIR / "capture_screenshots.yaml"
LOGO_PATH = SCRIPT_DIR.parent / "logo.png"

APP_ID = "net.kibotu.trail"
ACTIVITY = f"{APP_ID}/.MainActivity"

RAW_DIR = SCRIPT_DIR / "raw"
PHONE_DIR = SCRIPT_DIR / "phone"
TABLET_7_DIR = SCRIPT_DIR / "tablet"
TABLET_10_DIR = SCRIPT_DIR / "tablet-10"
FEATURE_DIR = SCRIPT_DIR / "feature"

BRAND_DARK = (15, 23, 42)
BRAND_BLUE = (30, 64, 175)
BRAND_LIGHT = (148, 163, 184)

PHONE_SIZE = (1080, 1920)
TABLET_7_SIZE = (1200, 1920)
TABLET_10_SIZE = (1600, 2560)
FEATURE_SIZE = (1024, 500)

SCREENS = [
    "screen_home",
    "screen_myfeed",
    "screen_profile",
    "screen_notifications",
    "screen_search",
    "screen_detail",
]

# ─── Font helpers ────────────────────────────────────────────────────


def get_font(size: int) -> ImageFont.FreeTypeFont:
    for name in ("DejaVuSans-Bold.ttf", "DejaVuSans.ttf"):
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    return ImageFont.load_default(size)


def get_font_regular(size: int) -> ImageFont.FreeTypeFont:
    try:
        return ImageFont.truetype("DejaVuSans.ttf", size)
    except OSError:
        return ImageFont.load_default(size)


# ─── Drawing helpers ─────────────────────────────────────────────────


def lerp_color(c1: tuple, c2: tuple, t: float) -> tuple:
    return tuple(int(c1[i] + (c2[i] - c1[i]) * t) for i in range(3))


def draw_gradient(img: Image.Image, top: tuple, bottom: tuple, horizontal: bool = False):
    draw = ImageDraw.Draw(img)
    w, h = img.size
    if horizontal:
        for x in range(w):
            draw.line([(x, 0), (x, h)], fill=lerp_color(top, bottom, x / w))
    else:
        for y in range(h):
            draw.line([(0, y), (w, y)], fill=lerp_color(top, bottom, y / h))


def draw_sparkles(draw: ImageDraw.Draw, w: int, h: int):
    """Draw decorative four-point stars matching the app's splash animation."""
    rng = random.Random(42)
    colors = [
        (251, 191, 36),   # gold
        (96, 165, 250),   # sky blue
        (52, 211, 153),   # leaf green
        (244, 114, 182),  # pink
        (167, 139, 250),  # purple
        (255, 255, 255),  # white
    ]
    for _ in range(25):
        cx, cy = rng.randint(0, w), rng.randint(0, h)
        size = rng.randint(3, 12)
        color = rng.choice(colors)
        alpha = rng.randint(80, 200)
        inner = size * 0.3
        points = [
            (cx, cy - size), (cx + inner, cy - inner),
            (cx + size, cy), (cx + inner, cy + inner),
            (cx, cy + size), (cx - inner, cy + inner),
            (cx - size, cy), (cx - inner, cy - inner),
        ]
        draw.polygon(points, fill=color + (alpha,))


# ─── Device detection ────────────────────────────────────────────────


def detect_device() -> str:
    result = subprocess.run(
        ["adb", "devices"], capture_output=True, text=True, check=True,
    )
    for line in result.stdout.strip().splitlines()[1:]:
        parts = line.split()
        if len(parts) >= 2 and parts[1] == "device":
            return parts[0]
    print("Error: No connected Android device found.")
    print("Connect a device or start an emulator, then try again.")
    sys.exit(1)


# ─── Screenshot capture ─────────────────────────────────────────────


def launch_app(device_id: str):
    """Force-stop and relaunch the app via adb."""
    subprocess.run(
        ["adb", "-s", device_id, "shell", "am", "force-stop", APP_ID],
        capture_output=True,
    )
    time.sleep(1)
    subprocess.run(
        ["adb", "-s", device_id, "shell", "am", "start", "-n", ACTIVITY],
        capture_output=True, check=True,
    )
    print(f"  Launched {APP_ID}, waiting for splash screen...")
    time.sleep(5)


def run_maestro(device_id: str):
    """Run the Maestro flow to capture screenshots."""
    if RAW_DIR.exists():
        shutil.rmtree(RAW_DIR)
    RAW_DIR.mkdir(parents=True)

    launch_app(device_id)

    print(f"  Running Maestro flow on {device_id}...")
    result = subprocess.run(
        [
            "maestro", "test",
            "--udid", device_id,
            "--debug-output", str(RAW_DIR),
            "--flatten-debug-output",
            "--test-output-dir", str(RAW_DIR),
            str(FLOW_FILE),
        ],
        cwd=str(SCRIPT_DIR),
    )

    screenshots_dir = RAW_DIR / "screenshots"
    if screenshots_dir.exists():
        for f in screenshots_dir.glob("*.png"):
            shutil.move(str(f), str(RAW_DIR / f.name))
        screenshots_dir.rmdir()

    found = list(RAW_DIR.glob("screen_*.png"))
    if result.returncode != 0:
        print(f"  Warning: Maestro exited with code {result.returncode}")
    if not found:
        print("  Error: No screenshots were captured.")
        sys.exit(1)
    print(f"  Captured {len(found)} screenshots")


# ─── Asset generation ────────────────────────────────────────────────


def _screen_label(name: str) -> str:
    """Turn 'screen_home' into 'home', 'screen_myfeed' into 'myfeed', etc."""
    return name.removeprefix("screen_")


def generate_feature_graphic():
    FEATURE_DIR.mkdir(parents=True, exist_ok=True)
    w, h = FEATURE_SIZE

    img = Image.new("RGBA", (w, h))
    draw_gradient(img, BRAND_DARK, BRAND_BLUE, horizontal=True)

    overlay = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    draw_sparkles(ImageDraw.Draw(overlay), w, h)
    img = Image.alpha_composite(img, overlay)

    if LOGO_PATH.exists():
        logo = Image.open(LOGO_PATH).convert("RGBA")
        logo = logo.resize((300, 300), Image.LANCZOS)
        img.paste(logo, (80, (h - 300) // 2), logo)

    draw = ImageDraw.Draw(img)
    font_title = get_font(80)
    font_sub = get_font_regular(28)

    text_x = 460
    bbox_t = draw.textbbox((0, 0), "Trail", font=font_title)
    bbox_s = draw.textbbox((0, 0), "Share. Discover. Connect.", font=font_sub)
    title_h = bbox_t[3] - bbox_t[1]
    sub_h = bbox_s[3] - bbox_s[1]
    total_h = title_h + 16 + sub_h
    title_y = (h - total_h) // 2
    sub_y = title_y + title_h + 16

    draw.text((text_x + 3, title_y + 3), "Trail", font=font_title, fill=(0, 0, 0, 100))
    draw.text((text_x, title_y), "Trail", font=font_title, fill=(255, 255, 255, 255))
    draw.text((text_x, sub_y), "Share. Discover. Connect.", font=font_sub, fill=BRAND_LIGHT + (255,))

    filename = f"feature_{w}x{h}.png"
    out_path = FEATURE_DIR / filename
    img.convert("RGB").save(out_path, "PNG")
    size_kb = out_path.stat().st_size / 1024
    print(f"  {filename} ({size_kb:.0f}KB)")


def generate_phone_screenshots():
    PHONE_DIR.mkdir(parents=True, exist_ok=True)
    target_w, target_h = PHONE_SIZE

    for name in SCREENS:
        src = RAW_DIR / f"{name}.png"
        if not src.exists():
            print(f"  Skip {name} (not found)")
            continue
        img = Image.open(src)
        w, h = img.size

        if h > target_h:
            crop_top = (h - target_h) // 2
            img = img.crop((0, crop_top, w, crop_top + target_h))
        if img.size[0] != target_w or img.size[1] != target_h:
            img = img.resize((target_w, target_h), Image.LANCZOS)

        filename = f"phone_{target_w}x{target_h}_{_screen_label(name)}.png"
        out_path = PHONE_DIR / filename
        img.save(out_path, "PNG")
        size_kb = out_path.stat().st_size / 1024
        print(f"  {filename} ({size_kb:.0f}KB)")


def _generate_tablet(out_dir: Path, target_w: int, target_h: int, label: str):
    out_dir.mkdir(parents=True, exist_ok=True)

    for name in SCREENS:
        src = PHONE_DIR / f"phone_{PHONE_SIZE[0]}x{PHONE_SIZE[1]}_{_screen_label(name)}.png"
        if not src.exists():
            print(f"  Skip {name} (not found)")
            continue
        phone = Image.open(src)
        pw, ph = phone.size

        tablet = Image.new("RGB", (target_w, target_h), BRAND_DARK)
        scale = target_h / ph
        scaled_w = int(pw * scale)
        phone_scaled = phone.resize((scaled_w, target_h), Image.LANCZOS)
        tablet.paste(phone_scaled, ((target_w - scaled_w) // 2, 0))

        filename = f"{label}_{target_w}x{target_h}_{_screen_label(name)}.png"
        out_path = out_dir / filename
        tablet.save(out_path, "PNG")
        size_kb = out_path.stat().st_size / 1024
        print(f"  {filename} ({size_kb:.0f}KB)")


def generate_tablet_7_screenshots():
    _generate_tablet(TABLET_7_DIR, *TABLET_7_SIZE, label="tablet-7")


def generate_tablet_10_screenshots():
    _generate_tablet(TABLET_10_DIR, *TABLET_10_SIZE, label="tablet-10")


# ─── Main ────────────────────────────────────────────────────────────


def main():
    parser = argparse.ArgumentParser(
        description="Generate Google Play Store listing assets for Trail",
    )
    parser.add_argument(
        "--device-id",
        help="Android device ID (auto-detected from adb if omitted)",
    )
    parser.add_argument(
        "--skip-capture",
        action="store_true",
        help="Skip Maestro capture, reuse existing raw/ screenshots",
    )
    args = parser.parse_args()

    device_id = args.device_id or detect_device()
    print(f"Device: {device_id}\n")

    if not args.skip_capture:
        print("=== Capturing Screenshots ===")
        run_maestro(device_id)
    else:
        found = list(RAW_DIR.glob("screen_*.png"))
        if not found:
            print(f"Error: --skip-capture but no screenshots in {RAW_DIR}/")
            sys.exit(1)
        print(f"Skipping capture, reusing {len(found)} screenshots in {RAW_DIR}/")

    print("\n=== Feature Graphic (1024x500) ===")
    generate_feature_graphic()

    print("\n=== Phone Screenshots (1080x1920, 9:16) ===")
    generate_phone_screenshots()

    print("\n=== 7-inch Tablet Screenshots (1200x1920) ===")
    generate_tablet_7_screenshots()

    print("\n=== 10-inch Tablet Screenshots (1600x2560, 9:16) ===")
    generate_tablet_10_screenshots()

    # Summary
    total = 0
    for d in (FEATURE_DIR, PHONE_DIR, TABLET_7_DIR, TABLET_10_DIR):
        for _ in d.glob("*.png"):
            total += 1

    print(f"\n{'='*50}")
    print(f"Done! Generated {total} assets:")
    print(f"  Feature:    {FEATURE_DIR.relative_to(SCRIPT_DIR)}/")
    print(f"  Phone:      {PHONE_DIR.relative_to(SCRIPT_DIR)}/")
    print(f"  Tablet 7\":  {TABLET_7_DIR.relative_to(SCRIPT_DIR)}/")
    print(f"  Tablet 10\": {TABLET_10_DIR.relative_to(SCRIPT_DIR)}/")


if __name__ == "__main__":
    main()
