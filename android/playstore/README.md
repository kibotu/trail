# Play Store Asset Generator

Generates Google Play Store listing assets for the Trail app by capturing live screenshots via [Maestro](https://maestro.mobile.dev/) and compositing them into store-ready images.

## Generated Assets

| Asset | Size | Output Directory |
|---|---|---|
| Feature graphic | 1024 x 500 | `feature/` |
| Phone screenshots | 1080 x 1920 (9:16) | `phone/` |
| 7-inch tablet screenshots | 1200 x 1920 | `tablet/` |
| 10-inch tablet screenshots | 1600 x 2560 (9:16) | `tablet-10/` |

Screenshots are captured for: Home, My Feed, Profile, Notifications, Search, and Entry Detail.

## Prerequisites

- **Python 3.12+** managed via [uv](https://docs.astral.sh/uv/)
- **Maestro CLI** — `brew install maestro`
- **ADB** — a connected Android device or running emulator
- **Logo** — `android/logo.png` (used in the feature graphic)

## Setup

```bash
# Install uv if you don't have it
brew install uv

# Create a virtual environment and install dependencies
cd android/playstore
uv venv
uv pip install Pillow
```

## Usage

```bash
# Full run: capture screenshots then generate all assets
uv run generate_playstore_assets.py

# Specify a device (skips auto-detection)
uv run generate_playstore_assets.py --device-id <DEVICE_ID>

# Skip capture and reuse existing screenshots in raw/
uv run generate_playstore_assets.py --skip-capture
```

### Flags

| Flag | Description |
|---|---|
| `--device-id <ID>` | Target a specific Android device (auto-detected via `adb devices` if omitted) |
| `--skip-capture` | Skip the Maestro flow and reuse screenshots already present in `raw/` |

## How It Works

1. **Capture** — The script launches the Trail app on the device, then runs the Maestro flow defined in `capture_screenshots.yaml` to navigate through the app and take screenshots into `raw/`.
2. **Feature graphic** — A branded 1024x500 image is composited from a gradient background, decorative sparkles, the app logo, and title text.
3. **Phone screenshots** — Raw captures are cropped/resized to 1080x1920.
4. **Tablet screenshots** — Phone screenshots are scaled and centered on a dark background at 7-inch (1200x1920) and 10-inch (1600x2560) dimensions.

## Maestro Flow

The capture flow (`capture_screenshots.yaml`) navigates through these screens in order:

1. Home screen
2. Entry detail (taps the first card)
3. My Feed
4. Profile
5. Notifications
6. Search (types "android" into the search bar)

To modify which screens are captured, edit `capture_screenshots.yaml` and update the `SCREENS` list in `generate_playstore_assets.py`.

## Output Structure

```
android/playstore/
├── feature/          # Feature graphic (1024x500)
├── phone/            # Phone screenshots (1080x1920)
├── tablet/           # 7" tablet screenshots (1200x1920)
├── tablet-10/        # 10" tablet screenshots (1600x2560)
├── raw/              # Raw Maestro captures (git-ignored)
├── capture_screenshots.yaml
├── generate_playstore_assets.py
└── README.md
```

All generated `.png` files and `raw/` are git-ignored.
