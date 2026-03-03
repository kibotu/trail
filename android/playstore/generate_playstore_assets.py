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
    from PIL import Image, ImageDraw, ImageFont, ImageFilter
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

SCREEN_COPY = {
    "screen_home": ("Welcome to Trail", "Your Micro Link Journal"),
    "screen_myfeed": ("Your Personal Feed", "Curate Your Interests"),
    "screen_profile": ("Your Digital Identity", "Express Yourself"),
    "screen_notifications": ("Stay in the Loop", "Never Miss an Update"),
    "screen_search": ("Discover Content", "Find What Matters"),
    "screen_detail": ("Dive Deeper", "Read and Share Links"),
}

# ─── Font helpers ────────────────────────────────────────────────────


def get_font(size: int) -> ImageFont.FreeTypeFont:
    for name in (
        "DejaVuSans-Bold.ttf",
        "DejaVuSans.ttf",
        "Arial.ttf",
        "Helvetica.ttc",
        "/System/Library/Fonts/Helvetica.ttc",
        "/Library/Fonts/Arial Unicode.ttf",
    ):
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    print(f"Warning: Could not load scalable font for size {size}, using default tiny font.")
    return ImageFont.load_default()


def get_font_regular(size: int) -> ImageFont.FreeTypeFont:
    for name in (
        "DejaVuSans.ttf",
        "Arial.ttf",
        "Helvetica.ttc",
        "/System/Library/Fonts/Helvetica.ttc",
        "/Library/Fonts/Arial Unicode.ttf",
    ):
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    return ImageFont.load_default()


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
    # Feature graphic font sizes
    font_title = get_font(130)
    font_sub = get_font_regular(48)

    # Calculate exact text dimensions to center it vertically
    if hasattr(draw, "textbbox"):
        bbox_t = draw.textbbox((0, 0), "Trail", font=font_title)
        bbox_s = draw.textbbox((0, 0), "Share. Discover. Connect.", font=font_sub)
        title_h = bbox_t[3] - bbox_t[1]
        sub_h = bbox_s[3] - bbox_s[1]
    else:
        _, title_h = draw.textsize("Trail", font=font_title)
        _, sub_h = draw.textsize("Share. Discover. Connect.", font=font_sub)

    gap = 5
    total_h = title_h + gap + sub_h
    # Shift title slightly down since uppercase letters create optical illusion
    title_y = (h - total_h) // 2 + 10
    sub_y = title_y + title_h + gap
    
    # Push text closer to the center, away from the logo
    text_x = 480

    # Stronger drop shadow for the main title to make it pop against the background
    draw.text((text_x + 5, title_y + 5), "Trail", font=font_title, fill=(0, 0, 0, 150))
    draw.text((text_x, title_y), "Trail", font=font_title, fill=(255, 255, 255, 255))
    
    draw.text((text_x, sub_y), "Share. Discover. Connect.", font=font_sub, fill=BRAND_LIGHT + (255,))

    filename = f"feature_{w}x{h}.png"
    out_path = FEATURE_DIR / filename
    img.convert("RGB").save(out_path, "PNG")
    size_kb = out_path.stat().st_size / 1024
    print(f"  {filename} ({size_kb:.0f}KB)")


def composite_screenshot(raw_img: Image.Image, target_w: int, target_h: int, title: str, subtitle: str) -> Image.Image:
    # Background
    img = Image.new("RGBA", (target_w, target_h))
    draw_gradient(img, BRAND_DARK, BRAND_BLUE, horizontal=False)
    
    overlay = Image.new("RGBA", (target_w, target_h), (0, 0, 0, 0))
    draw_sparkles(ImageDraw.Draw(overlay), target_w, target_h)
    img = Image.alpha_composite(img, overlay)
    
    # Text
    draw = ImageDraw.Draw(img)
    # responsive font sizes
    title_size = int(target_w * 0.08)
    sub_size = int(target_w * 0.04)
    font_title = get_font(title_size)
    font_sub = get_font_regular(sub_size)
    
    text_y_start = int(target_h * 0.06)
    
    if hasattr(draw, "textbbox"):
        bbox_t = draw.textbbox((0, 0), title, font=font_title)
        bbox_s = draw.textbbox((0, 0), subtitle, font=font_sub)
        t_w = bbox_t[2] - bbox_t[0]
        s_w = bbox_s[2] - bbox_s[0]
        title_h = bbox_t[3] - bbox_t[1]
    else:
        t_w, title_h = draw.textsize(title, font=font_title)
        s_w, _ = draw.textsize(subtitle, font=font_sub)
    
    draw.text(((target_w - t_w) // 2, text_y_start), title, font=font_title, fill=(255, 255, 255, 255))
    draw.text(((target_w - s_w) // 2, text_y_start + title_h + int(target_h * 0.015)), subtitle, font=font_sub, fill=BRAND_LIGHT + (255,))
    
    # Screenshot Frame
    screen_y_start = text_y_start + title_h + sub_size + int(target_h * 0.04)
    screen_margin_x = int(target_w * 0.1)
    avail_w = target_w - (screen_margin_x * 2)
    avail_h = target_h - screen_y_start - int(target_h * 0.05)
    
    rw, rh = raw_img.size
    
    # Scale screenshot to fit inside
    scale = min(avail_w / rw, avail_h / rh)
    new_w, new_h = int(rw * scale), int(rh * scale)
    
    screen_x = (target_w - new_w) // 2
    screen_y = screen_y_start
    
    screen_resized = raw_img.resize((new_w, new_h), Image.LANCZOS)
    
    # Create mask for rounded corners
    radius = int(target_w * 0.03)
    mask = Image.new("L", (new_w, new_h), 0)
    mask_draw = ImageDraw.Draw(mask)
    if hasattr(mask_draw, "rounded_rectangle"):
        mask_draw.rounded_rectangle((0, 0, new_w, new_h), radius=radius, fill=255)
    else:
        # Fallback to simple rectangle for very old PIL
        mask_draw.rectangle((0, 0, new_w, new_h), fill=255)
    
    # Paste screenshot into a transparent layer using mask
    screen_layer = Image.new("RGBA", (new_w, new_h), (0, 0, 0, 0))
    screen_layer.paste(screen_resized, (0, 0))
    
    # Drop shadow
    shadow_offset = int(target_w * 0.015)
    shadow_radius = int(target_w * 0.02)
    shadow_img = Image.new("RGBA", (target_w, target_h), (0, 0, 0, 0))
    s_draw = ImageDraw.Draw(shadow_img)
    if hasattr(s_draw, "rounded_rectangle"):
        s_draw.rounded_rectangle(
            (screen_x + shadow_offset, screen_y + shadow_offset, screen_x + new_w + shadow_offset, screen_y + new_h + shadow_offset),
            radius=radius,
            fill=(0, 0, 0, 80)
        )
    else:
        s_draw.rectangle(
            (screen_x + shadow_offset, screen_y + shadow_offset, screen_x + new_w + shadow_offset, screen_y + new_h + shadow_offset),
            fill=(0, 0, 0, 80)
        )
    shadow_img = shadow_img.filter(ImageFilter.GaussianBlur(shadow_radius))
    
    # Composite shadow
    img = Image.alpha_composite(img, shadow_img)
    
    # Composite screenshot
    final_layer = Image.new("RGBA", (target_w, target_h), (0, 0, 0, 0))
    final_layer.paste(screen_layer, (screen_x, screen_y), mask)
    img = Image.alpha_composite(img, final_layer)
    
    return img.convert("RGB")


def _generate_device_screenshots(out_dir: Path, target_w: int, target_h: int, label: str):
    out_dir.mkdir(parents=True, exist_ok=True)
    
    for name in SCREENS:
        src = RAW_DIR / f"{name}.png"
        if not src.exists():
            print(f"  Skip {name} (not found)")
            continue
        
        raw_img = Image.open(src)
        title, subtitle = SCREEN_COPY.get(name, ("Trail App", "Discover More"))
        
        final_img = composite_screenshot(raw_img, target_w, target_h, title, subtitle)
        
        filename = f"{label}_{target_w}x{target_h}_{_screen_label(name)}.png"
        out_path = out_dir / filename
        final_img.save(out_path, "PNG")
        size_kb = out_path.stat().st_size / 1024
        print(f"  {filename} ({size_kb:.0f}KB)")


def generate_phone_screenshots():
    _generate_device_screenshots(PHONE_DIR, *PHONE_SIZE, label="phone")


def generate_tablet_7_screenshots():
    _generate_device_screenshots(TABLET_7_DIR, *TABLET_7_SIZE, label="tablet-7")


def generate_tablet_10_screenshots():
    _generate_device_screenshots(TABLET_10_DIR, *TABLET_10_SIZE, label="tablet-10")


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

    if not args.skip_capture:
        device_id = args.device_id or detect_device()
        print(f"Device: {device_id}\n")
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