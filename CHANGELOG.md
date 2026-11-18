# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.3.0] - 2026-11-18

### Changed

- Search now combines tags and text instead of picking one. `?q=` is split by `SearchService::parseQuery()` into free text and `#tag` tokens, and every tag is ANDed with the text. Applies to `/api/entries`, `/api/users/{nickname}/entries` and `/api/collections/{slug}/entries`.
- Tag chips append to the search field instead of replacing it: `memory` + a click on `#openai` yields `memory #openai`. Landing page, user profile, collection page and embeds.
- `Entry::searchAll`, `searchByUser`, `countSearchAll` and `countSearchByUser` take a tag slug list. `searchByTagSlug`, `searchByTagSlugWithImages` and `countByTagSlug` removed: tag queries now run through the same code path.
- Dependencies raised to current releases. Backend: `firebase/php-jwt` 7.2.2, `slim/slim` 4.15.3, `slim/psr7` 1.8.1, `embed/embed` 4.6.2, `enshrined/svg-sanitize` 1.1.1, `symfony/yaml` 8.1.11, PHPStan 2.3.6, PHPUnit 13.4.7, PHP floor 8.4.1. Android: `FontAwesomeCompose` 1.0.0 → 1.1.0. Helper scripts: raised floors in `entry-rewriter`, `topic-generation` and `twitter-migration` (requests 2.34.2, urllib3 2.8.0, trafilatura 2.3.1, pyyaml 6.0.3). Four call sites adjusted with them: `UrlEmbedService` drops the now-redundant `?? null` on `providerName`, `JwtServiceTest` secrets are lengthened to the 32 bytes php-jwt 7 requires for HS256, and PHPStan 2.3 no longer needs the null guards in `EntryController` and `AuthMiddleware`.

### Fixed

- Tag links on the server-rendered first page no longer swallow the click. `searchManager` was declared inside the feed block, out of reach of `hydrateEntryCard`, which had already called `preventDefault()`.
- Tapping a trail link in the Android app opens the page it names instead of the share composer. `/status/{hashId}` goes to `entry/{hashId}`, `/@{nickname}` to `user/{nickname}`, `/collection/{slug}` to `collection/{slug}`. Shared text and every other path keep the composer.
- Tag-based collection attribution is scoped to the collection owner. A tag shared by a collection and by someone else's entry used to pull that entry into the collection: it showed up in the collection's list and inflated its `entry_count`. The ownership check now sits in the join in `Entry::COLLECTION_CLAIM_JOIN_SQL` and `Collection::COLLECTION_SELECT`, so other accounts' entries keep their poster attribution.

### Added

- `SearchService::parseQuery()`, `SearchManager.appendTag()`.
- `SearchServiceTest` (query parsing) and `TagSearchTest` (combined tag and text queries over the live API).
- `TrailDeepLink.routeFor()` (App Link path → nav route) and `TrailDeepLinkTest`.
- `CollectionsIntegrationTest::testNonOwnerEntriesAreNotClaimed`. Needs `TRAIL_ADMIN_API_TOKEN` and `TRAIL_USER_API_TOKEN`; skips without them.

## [2.2.1] - 2026-09-17

### Added

- Collections sidebar on the landing page: a sticky rail of avatar bubbles (top 20 collections by entry count) with a "view all" link. Desktop shows a 3-wide grid; mobile collapses to a horizontal-scroll strip behind a disclosure toggle. The sidebar is server-rendered; the only client JS is the mobile expand/collapse.
- `GET /api/collections/sidebar` — public endpoint returning top collections sorted by entry count descending (capped at 20, 5-min cache). Minimal payload: `name`, `slug`, `avatar_url`, `entry_count`.
- `Collection::getAllByEntryCount()` model method and `SIDEBAR_LIMIT` constant.
- Integration test for `/api/collections/sidebar` (verifies cap, sort order, cache header, minimal payload).
- Unit test asserting `sidebar` is a reserved collection slug.

### Changed

- Landing page layout restructured to a flex container (`.landing-layout`) with the feed and sidebar as siblings. Max width raised to 1100px when sidebar is present.
- `sync.sh` landing bundle now includes `collections-sidebar.js`.
- `Collection::RESERVED_SLUGS` updated with `sidebar`.

## [2.2.0] - 2026-09-17

### Added

- Collections sidebar component for browsing and filtering collections.

- Android: shared element transition from entry list cards to the detail screen. Tapping an entry now animates the card itself (bounds + content crossfade) instead of a plain screen slide. Only the tapped entry participates, so the same entry appearing in multiple feeds won't collide on a tab switch.
- Android: `EntryCache` — an in-memory LRU map (200 entries) that seeds the detail screen with the card's data on the first frame, eliminating the shimmer flash while the network round-trip completes. Cache is populated by `EntriesPagingSource`, `UserEntriesPagingSource` and `EntryDetailViewModel`.
- Android: `TrailMotion` — centralised navigation animation definitions. Three motion families: tabs fade-through, entry detail fades only (shared bounds carry the eye), pushed screens slide from the right with a parallaxing parent. Pop and predictive-pop use the same values.
- Android: `BackButton` composable extracted from entry detail, user profile, and collection screens. Back arrow now slides in/out sideways via `animateEnterExit` instead of riding the screen transition.
- Android: tab bar enters/exits with a slide-up + fade animation (`AnimatedVisibility`) instead of popping in instantly.

### Changed

- Android: navigation refactored — all destinations now register through a `screen()` helper that provides `SharedTransitionScope` and `AnimatedVisibilityScope` via composition locals. `NavHost` transition lambdas delegate to `TrailMotion`.
- Android: entry detail, user profile, and collection screens render an opaque `MaterialTheme.colorScheme.background` behind the content so the screen underneath cannot show through during crossfade transitions.
- Android: removed inline back-button boilerplate from `EntryDetailScreen`, `UserProfileScreen`, and `CollectionScreen` (replaced by shared `BackButton`).

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

[unreleased]: https://github.com/kibotu/trail/compare/2.2.1...HEAD
[2.2.1]: https://github.com/kibotu/trail/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/kibotu/trail/compare/2.1.3...2.2.0
[2.1.3]: https://github.com/kibotu/trail/compare/2.1.2...2.1.3
[2.1.2]: https://github.com/kibotu/trail/compare/2.1.0...2.1.2
[2.1.0]: https://github.com/kibotu/trail/compare/2.0.2...2.1.0
[2.0.2]: https://github.com/kibotu/trail/compare/2.0.1...2.0.2
[2.0.1]: https://github.com/kibotu/trail/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/kibotu/trail/compare/1.2.7...2.0.0
[1.2.7]: https://github.com/kibotu/trail/releases/tag/1.2.7
