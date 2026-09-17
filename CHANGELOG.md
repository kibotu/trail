# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.3] - 2026-09-17

### Fixed

- Android: video player seekbar was positioned in the middle of the view instead of the bottom. Control overlay visibility was coupled to the paused state, causing the entire controls layer (gradient scrim, seekbar, buttons) to render simultaneously with the centered play button when paused.
- Android: video player controls Row wrapped to multiple lines. The fullscreen IconButton had `systemBarsPadding()` applied directly, injecting ~48dp of insets into a single button and forcing the Row to wrap.
- Android: video was stuck with a spinner when returning from fullscreen to list view. Two `PlayerView` instances shared the same `ExoPlayer`; `setPlayer()` is a no-op when the player is already set, so the inline `PlayerView` never re-acquired the video surface after the dialog was dismissed. Now only one `PlayerView` exists at a time.
- Android: replay button did not restart playback after returning from fullscreen (same surface acquisition issue).

### Changed

- Android: gradient scrim now covers the full overlay area (transparent → opaque black from top to bottom) instead of only the Column's intrinsic height.
- Android: controls layout reordered — play/pause, timestamp, mute and fullscreen buttons sit above the seekbar, which is flush at the bottom edge of the video.

## [2.1.2] - 2026-09-16

### Changed

- Android: updated Gradle wrapper, dependencies and library versions.

## [2.1.0] - 2026-09-16

### Added

- Android: collection support in the main feed — entries claimed by a collection show the collection's avatar and name instead of the poster's. Tapping navigates to a new collection detail page with header, avatar, bio, tag listing (collapsed by default), entry/view stats, RSS link, and paginated entry feed.

## [2.0.2] - 2026-09-16

### Added

- Collections: tag-defined groups that attribute entries to a collection identity instead of the poster. Each collection has its own page, avatar, header image, bio, tags and view counts (migration 036).
- Public API: `GET /api/collections`, `GET /api/collections/{slug}`, `GET /api/collections/{slug}/entries` (cursor pagination, optional `?q=` search), `GET /api/collections/{slug}/rss`, `POST /api/collections/{slug}/views`.
- Admin API: `POST /api/admin/collections`, `PUT /api/admin/collections/{id}`, `DELETE /api/admin/collections/{id}`. Deleting reverts claimed entries to poster attribution.
- Collection page `/collection/{slug}`, collections directory `/collections`, embeddable widget `/collection/{slug}/embed`.
- Dev login without Google OAuth (`dev-login.php`), gated by `environment=development` + `dev_login.enabled` + localhost.
- Local dev stack: `docker-compose.yml` for the DB, `run.sh` + `router.php` for the PHP built-in server on :18000.
- Unit tests for `Collection` (slug validation, slugify, reserved slugs) and RSS generator channel overrides; HTTP integration tests for the collection API.
- Image crop modal (`image-crop.js`) using Cropper.js v1.6.2 for pre-upload cropping of profile headers, avatars and collection images. Outputs lossless PNG to the server for a single WebP encode pass.
- Collection page admin editing: admins see upload overlays (header + avatar) with hover effects matching the user profile page. Click to crop and upload directly from `/collection/{slug}`.
- `personas/upsert-collections.sh` and updated `create-collections.php`: bulk upsert all persona collections from `personas.md` with tag linking and `--avatars` flag for uploading avatar images from `personas/avatars/` via the chunked upload API.

### Changed

- Entry feeds (`/`, `/api/entries`, `/api/rss`) now include a `collection` object on claimed entries and show collection identity (name, avatar, `/collection/{slug}` link) instead of poster attribution. Poster pages (`/@{nickname}`) are unchanged.
- RSS author field shows the collection name for claimed entries; item guids and permalinks use `hash_id` when available.
- `embed-behaviors.js` extracted from `embed-page.js` and shared between the user embed and collection embed (auto-resize, click overrides, share modal).
- `RssGenerator::generate()` accepts channel title and link overrides.
- Header image crop aspect ratio changed from 4.8 to 3.68 to match the display container (736×200), eliminating side-cropping by `background-size: cover`.
- Header image upload outputs lossless PNG from the crop canvas instead of WebP, avoiding a double lossy encode (crop→WebP 92% → server→WebP 90%).
- Collection page header image height unified to 200px on mobile (was 150px), matching desktop.
- `sync.sh` bundles: added `image-crop.js` to user and collection bundles, `image-upload.js` to collection bundle.
- Avatar container z-index bumped above header upload overlay to prevent the overlay from rendering over the avatar.

### Fixed

- New users get an `api_token` on creation (NOT NULL since migration 026): Google OAuth callback, dev login and `User::create`.
- Collection page `updateCollection()` now preserves existing `avatar_image_id` and `header_image_id` when patching a single field, preventing avatar/header loss on partial updates.
- Collection page upload overlays use the `owner` CSS class (matching user profile page) instead of inline `style.display`, enabling the hover opacity transition with camera icon.
- Crop modal uses `FileReader.readAsDataURL()` instead of `URL.createObjectURL()` to comply with CSP `img-src` policy (blocks `blob:` URLs).
- `create-collections.php` token parsing: first positional argument (not a flag) is used as the API token, preventing `--avatars` from being interpreted as the token.

## [2.0.1] - 2026-08-21

### Fixed

- Android app crash fixes in search, profile caching and theme preferences.
- In-app review and update manager stability improvements.

## [2.0.0] - 2026-08-21

### Added

- Android: image download support for offline viewing.
- Android: pull-to-refresh on profile screen.
- Android: ruler tool in entry detail view.
- Unit tests for `ProfileViewModel` and `ProfileRepository`.

### Changed

- Android Gradle and dependency version updates.
- Improved profile caching strategy.
- Backend `.htaccess` rewrite rules updated.

## [1.2.7] - 2026-03-18

### Added

- Admin dashboard (`/admin`) with entry management UI and AI script controls.
- Redesigned entry card component with richer metadata display.
- GitHub Actions CI trigger for automated builds.

### Changed

- Optimized first page load and improved static asset caching.
- `created_at` field now optional in entry creation.

[unreleased]: https://github.com/kibotu/trail/compare/2.1.3...HEAD
[2.1.3]: https://github.com/kibotu/trail/compare/2.1.2...2.1.3
[2.1.2]: https://github.com/kibotu/trail/compare/2.1.0...2.1.2
[2.1.0]: https://github.com/kibotu/trail/compare/2.0.2...2.1.0
[2.0.2]: https://github.com/kibotu/trail/compare/2.0.1...2.0.2
[2.0.1]: https://github.com/kibotu/trail/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/kibotu/trail/compare/1.2.7...2.0.0
[1.2.7]: https://github.com/kibotu/trail/releases/tag/1.2.7
