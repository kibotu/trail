# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Collections: tag-defined groups that attribute entries to a collection identity instead of the poster. Each collection has its own page, avatar, header image, bio, tags and view counts (migration 036).
- Public API: `GET /api/collections`, `GET /api/collections/{slug}`, `GET /api/collections/{slug}/entries` (cursor pagination, optional `?q=` search), `GET /api/collections/{slug}/rss`, `POST /api/collections/{slug}/views`.
- Admin API: `POST /api/admin/collections`, `PUT /api/admin/collections/{id}`, `DELETE /api/admin/collections/{id}`. Deleting reverts claimed entries to poster attribution.
- Collection page `/collection/{slug}`, collections directory `/collections`, embeddable widget `/collection/{slug}/embed`.
- Dev login without Google OAuth (`dev-login.php`), gated by `environment=development` + `dev_login.enabled` + localhost.
- Local dev stack: `docker-compose.yml` for the DB, `run.sh` + `router.php` for the PHP built-in server on :18000.
- Unit tests for `Collection` (slug validation, slugify, reserved slugs) and RSS generator channel overrides; HTTP integration tests for the collection API.

### Changed

- Entry feeds (`/`, `/api/entries`, `/api/rss`) now include a `collection` object on claimed entries and show collection identity (name, avatar, `/collection/{slug}` link) instead of poster attribution. Poster pages (`/@{nickname}`) are unchanged.
- RSS author field shows the collection name for claimed entries; item guids and permalinks use `hash_id` when available.
- `embed-behaviors.js` extracted from `embed-page.js` and shared between the user embed and collection embed (auto-resize, click overrides, share modal).
- `RssGenerator::generate()` accepts channel title and link overrides.

### Fixed

- New users get an `api_token` on creation (NOT NULL since migration 026): Google OAuth callback, dev login and `User::create`.
