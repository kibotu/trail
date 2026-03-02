# Trail

[![Build](https://github.com/kibotu/trail/actions/workflows/build.yml/badge.svg)](https://github.com/kibotu/trail/actions/workflows/build.yml)
[![Backend](https://github.com/kibotu/trail/actions/workflows/backend.yml/badge.svg)](https://github.com/kibotu/trail/actions/workflows/backend.yml)
[![Release](https://github.com/kibotu/trail/actions/workflows/release.yml/badge.svg)](https://github.com/kibotu/trail/actions/workflows/release.yml)

A self-hosted micro link journal. Multi-user, chronological, yours.

**Live demo:** https://trail.services.kibotu.net

---

**TL;DR for the impatient:**

```bash
# Backend
git clone https://github.com/kibotu/trail.git && cd trail/backend
composer install && cp secrets.yml.example secrets.yml
# Edit secrets.yml, create database, then: cd .. && ./sync.sh

# Android
cd android && ./gradlew assembleDebug installDebug  # Debug APK
./gradlew bundleRelease                             # Play Store bundle
```

---

## Why

Personal link journaling without algorithmic feeds. Share what matters, own your data, control your timeline. Built for self-hosting hobbyists who want a simple, chronological space to collect links, thoughts, and images.

**What makes Trail different:**

- **Chronological only** — No algorithms, no engagement optimization, no dark patterns. Posts appear in the order they were created. Revolutionary, we know.
- **140 characters** — Constraints breed creativity. If you need more, you're probably overthinking it.
- **Self-hosted first** — Your server, your rules, your data. No venture capital, no pivot to ads, no "we're shutting down" emails.
- **Multi-user** — Not just a personal journal. Invite friends, family, or your entire homelab Discord.
- **Native Android app** — Because PWAs are fine, but native apps are better. Share from anywhere, post from your phone, no browser tabs.
- **RSS feeds** — Because some of us still use feed readers like it's 2008. And that's okay.

## Features

### Core

- **140-character posts** with automatic URL card previews (powered by Iframely)
- **Up to 3 images** per post/comment (WebP-optimized, 20MB max each)
- **Claps** (Medium-style, 1-50 per entry), threaded comments, @mentions
- **View counts** on entries, comments, and profiles
- **Per-user pages** (`/@username`) and global chronological feed
- **Full-text search** with relevance ranking
- **Customizable profiles** (avatar, header image, bio)
- **Notification system** (claps, mentions)
- **User muting** and content reporting
- **RSS feeds** (global + per-user)
- **Google OAuth 2.0** + persistent API tokens
- **Twitter/X archive migration** (one command)
- **Account deletion** with grace period and **account restore**
- **Data export** — download all your data
- [**Data Privacy**](https://trail.services.kibotu.net/data-privacy/) and [**Terms & Conditions**](https://trail.services.kibotu.net/terms-and-conditions/)

### Embedding

- **Profile widget** (light/dark theme, transparent background, auto-resize)
- **Responsive iframe** with auto-height messaging
- **Configurable** header, search, pagination

### Android App

- **Share intent** — Post from any app (text → Share → Trail)
- **Dual feeds** — Global timeline + personal `/@username` feed
- **Rich media** — Images, GIFs, video playback (inline/fullscreen)
- **Offline-first auth** — Google Sign-In with JWT token persistence
- **Material 3** — Light/dark theme, edge-to-edge, animated splash
- **Baseline profiles** — Optimized cold start and runtime performance

## Stack

**Backend:** PHP 8.4+ • Slim 4 • MariaDB • JWT • Iframely API  
**Android:** Kotlin 2.3 • Jetpack Compose • Ktor • Material 3

## Quick Start

### Backend

```bash
# 1. Clone and install
git clone https://github.com/kibotu/trail.git
cd trail/backend
composer install

# 2. Create database
mysql -u root -p
CREATE DATABASE trail_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 3. Configure
cp secrets.yml.example secrets.yml
# Edit secrets.yml with your database, Google OAuth, and JWT settings

# 4. Deploy
cd ..
./sync.sh
```

See [backend/README.md](backend/README.md) for complete deployment instructions.

### Android

**Requirements:** Android 6.0+ (API 23), JDK 17, Android Studio Ladybug+ (or command-line tools)

```bash
cd android

# 1. Configure (create local.properties)
cat > local.properties << EOF
API_BASE_URL=https://your-trail-instance.example.com/
DEBUG_KEYSTORE_PATH=certificates/debug.jks
DEBUG_STORE_PASSWORD=your_password
DEBUG_KEYSTORE_ALIAS=debug
DEBUG_KEY_PASSWORD=your_password
EOF

# 2. Build & install debug APK
./gradlew assembleDebug installDebug

# 3. Build release APK (minified, R8)
./gradlew assembleRelease

# 4. Build app bundle for Play Store
./gradlew bundleRelease
```

**Outputs:**  
APKs → `app/build/outputs/apk/`  
Bundles → `app/build/outputs/bundle/`

**Note:** Also update `app/src/main/res/values/strings.xml` → `default_web_client_id` with your Google OAuth Web Client ID (from Cloud Console → Credentials).

See [android/README.md](android/README.md) for architecture details.

#### Signing Configuration

Generate keystores for debug and release builds:

```bash
# Debug keystore (for development)
keytool -genkey -v -keystore android/certificates/debug.jks \
  -alias debug -keyalg RSA -keysize 2048 -validity 10000 \
  -storepass your_password -keypass your_password

# Release keystore (for production)
keytool -genkey -v -keystore android/certificates/release.jks \
  -alias release -keyalg RSA -keysize 2048 -validity 10000 \
  -storepass your_password -keypass your_password
```

Then update `android/local.properties` with the paths and passwords.

**Pro tip:** For CI/CD, encode keystores as base64 and store in secrets:

```bash
base64 -i certificates/release.jks | pbcopy  # macOS
base64 -w 0 certificates/release.jks         # Linux
```

## Twitter Migration

Migrate your Twitter/X archive to Trail in one command:

```bash
cd twitter
./migrate.sh --api-key YOUR_API_KEY --archive twitter-backup.zip
```

See [twitter/README.md](twitter/README.md) for details.

## Embedding

Add your Trail feed to any website with a single iframe:

```html
<iframe src="https://trail.services.kibotu.net/@kibotu/embed?theme=dark"
        style="border:none; width:100%; min-width:320px;"
        loading="lazy"
        allow="web-share; clipboard-write"></iframe>
```

| Parameter | Values | Default | Description |
|-----------|--------|---------|-------------|
| `theme` | `light`, `dark` | `dark` | Color scheme (transparent background) |
| `header` | `0`, `1` | `0` | Show profile avatar, bio, and stats |
| `search` | `0`, `1` | `0` | Show search bar |
| `limit` | `1`-`50` | `20` | Entries per page |

The embed posts `trail-embed-resize` messages for auto-height. A ready-to-copy snippet with the resize script is available on your `/profile` page under "Embed Your Profile".

## API

Full REST API with public and authenticated endpoints. Generate an API token from your profile page.

**Documentation:** https://trail.services.kibotu.net/api

## Build Commands Reference

### Android Gradle Tasks

```bash
# Development
./gradlew assembleDebug              # Build debug APK
./gradlew installDebug               # Install debug APK to device
./gradlew uninstallDebug             # Uninstall debug APK

# Release
./gradlew assembleRelease            # Build release APK (minified, R8)
./gradlew bundleRelease              # Build app bundle for Play Store
./gradlew installRelease             # Install release APK to device

# Release with custom version (for manual builds)
./gradlew assembleRelease -PversionName=1.0.0 -PversionCode=10000001
./gradlew bundleRelease -PversionName=1.0.0 -PversionCode=10000001

# Testing
./gradlew test                       # Run unit tests
./gradlew connectedAndroidTest       # Run instrumented tests

# Code Quality
./gradlew lint                       # Run Android linter
./gradlew lintDebug                  # Lint debug variant
./gradlew lintRelease                # Lint release variant

# Baseline Profiles (performance optimization)
./gradlew generateBaselineProfile    # Generate baseline profile
./gradlew :baselineprofile:pixel6Api36Setup  # Setup managed device
# Note: Baseline profiles improve cold start by ~30% and reduce jank.
# They're auto-generated during release builds and committed to src/main/.

# Cleanup
./gradlew clean                      # Clean build artifacts
./gradlew cleanBuildCache            # Clean Gradle build cache

# Diagnostics
./gradlew dependencies               # Show dependency tree
./gradlew logVersionOverrides        # Analyze version conflicts
./gradlew tasks --all                # List all available tasks
```

### App Bundle Notes

App bundles (`.aab`) are the preferred format for Google Play Store distribution. They enable:

- **Dynamic delivery** — Users download only the APK splits for their device (density, ABI, language)
- **Smaller downloads** — ~15-30% smaller than universal APKs
- **Automatic optimization** — Google Play generates optimized APKs per device config

The `bundleRelease` task produces `app/build/outputs/bundle/release/app-release.aab`.

For local testing of bundles, use [bundletool](https://github.com/google/bundletool):

```bash
# Generate APKs from bundle
bundletool build-apks --bundle=app-release.aab --output=app.apks

# Install to connected device
bundletool install-apks --apks=app.apks
```

## Project Structure

```
trail/
├── android/           # Android app (Kotlin, Compose)
│   ├── app/           # Main application module
│   ├── baselineprofile/  # Performance optimization profiles
│   └── local.properties  # Config (not in git)
├── backend/           # PHP API (Slim 4)
│   ├── public/        # Web root
│   ├── src/           # Controllers, Models, Services
│   └── secrets.yml    # Configuration (not in git)
├── twitter/           # Archive importer
│   ├── migrate.sh     # Migration script
│   └── README.md
├── migrations/        # SQL migrations
└── sync.sh            # Deployment script
```

## Troubleshooting

### Android Build Issues

**Problem:** `AAPT: error: resource android:attr/lStar not found`  
**Solution:** Upgrade to compileSdk 31+ (app targets SDK 36)

**Problem:** `Cleartext HTTP traffic not permitted`  
**Solution:** HTTPS is enforced by `network_security_config.xml`. For local dev, add your IP to the config or use `ngrok`/`localtunnel`.

**Problem:** Google Sign-In fails with `DEVELOPER_ERROR`  
**Solution:** Verify `default_web_client_id` in `strings.xml` matches your Google Cloud Console OAuth 2.0 Web Client ID (not Android client ID).

**Problem:** Gradle build is slow  
**Solution:** Already optimized with configuration cache, parallel execution, and build cache. For faster incremental builds, avoid `clean` unless necessary. First build takes longer (downloads dependencies, generates baseline profiles).

**Problem:** Configuration cache warnings  
**Solution:** Harmless. The build uses `org.gradle.configuration-cache=true` for speed. Warnings are set to `warn` level in `gradle.properties`.

**Problem:** App bundle upload rejected by Play Console  
**Solution:** Ensure `versionCode` is incremented in `app/build.gradle.kts` for each release.

### Backend Issues

**Problem:** API returns 401 Unauthorized  
**Solution:** Check JWT token expiry. The Android app auto-refreshes on 401, but verify `secrets.yml` has correct `jwt_secret`.

**Problem:** Image uploads fail  
**Solution:** Check PHP `upload_max_filesize` and `post_max_size` (should be ≥20MB). Verify write permissions on `backend/public/uploads/`.

**Problem:** Iframely previews not working  
**Solution:** Requires paid Iframely API key in `secrets.yml`. Free tier has rate limits.

## Self-Hosting Tips

- **Reverse proxy:** Use Caddy or nginx with automatic HTTPS (Let's Encrypt). Trail expects to run at domain root or subdirectory.
- **Database backups:** Automate with `mysqldump` cron job. Trail stores everything in MariaDB (no filesystem dependencies except uploads).
- **Android app distribution:** Self-hosters can distribute APKs directly (no Play Store required). Consider [F-Droid](https://f-droid.org/en/docs/Setup_an_F-Droid_App_Repo/) or [Obtainium](https://github.com/ImranR98/Obtainium) for updates.
- **Multi-instance:** Each Android app can connect to different backends by changing `API_BASE_URL` in `local.properties` and rebuilding.
- **Performance:** Enable MariaDB query cache and PHP opcache. For high traffic, add Redis for session storage.

## Contributing

PRs welcome. For major changes, open an issue first. Follow existing code style (PSR-12 for PHP, Kotlin official style guide for Android).

Run tests before submitting:

```bash
# Backend
cd backend && composer test

# Android
cd android && ./gradlew test lint
```

## License

Apache 2.0
