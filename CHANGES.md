# Character Limit Configuration Changes

## Summary
Changed the maximum character limit for entries and comments from **280 to 140 characters** and made it configurable through `backend/secrets.yml`.

## Changes Made

### 1. Configuration File
**File:** `backend/secrets.yml`
- Added `max_text_length: 140` under the `app` section
- This is now the single source of truth for character limits

### 2. Backend Changes

#### Config Class
**File:** `backend/src/Config/Config.php`
- Added `getMaxTextLength()` method to retrieve the max text length from config
- Returns 140 as default if not set

#### Controllers
Updated to read max text length from config:
- `backend/src/Controllers/EntryController.php`
  - Updated validation in `create()` and `update()` methods
  - Dynamic error messages showing current limit
- `backend/src/Controllers/CommentController.php`
  - Updated validation in `create()` and `update()` methods
  - Dynamic error messages showing current limit
- `backend/src/Controllers/AdminController.php`
  - Updated validation in admin entry update

#### API Endpoint
**File:** `backend/public/index.php`
- Added new endpoint: `GET /api/config`
- Returns: `{"max_text_length": 140}`
- Allows frontend to dynamically fetch configuration

### 3. Frontend Changes

#### New Config Module
**File:** `backend/public/js/config.js`
- New module for loading configuration from API
- Functions:
  - `loadConfig()` - Fetches and caches config from `/api/config`
  - `getMaxTextLength()` - Returns max text length
  - `getConfigSync()` - Synchronous config access (requires preload)
- Caches config in `window.appConfig` to avoid repeated API calls

#### Templates Updated
All templates now include `config.js` and use dynamic limits:

1. **backend/templates/public/landing.php**
   - Added `<script src="/js/config.js"></script>`
   - Removed hardcoded `maxlength="280"`
   - Updated initial counter display to `0 / 140`

2. **backend/templates/public/error.php**
   - Added `<script src="/js/config.js"></script>`
   - Removed hardcoded `maxlength="280"`
   - Updated counter display to `140`

3. **backend/public/admin/index.php**
   - Added `<script src="/js/config.js"></script>`
   - Added `MAX_TEXT_LENGTH` variable loaded from config
   - Updated edit form to use dynamic `maxlength="${MAX_TEXT_LENGTH}"`
   - Updated character counter to use `MAX_TEXT_LENGTH`

#### JavaScript Files Updated
All JS files now use dynamic config values:

1. **backend/public/js/landing-page.js**
   - Changed to async IIFE
   - Loads config on initialization
   - Sets `maxlength` attribute dynamically
   - Uses `maxTextLength` in validation and counters

2. **backend/public/js/error-page.js**
   - Changed to async IIFE
   - Loads config on initialization
   - Sets `maxlength` attribute dynamically
   - Uses `maxTextLength` in validation

3. **backend/public/js/entries-manager.js**
   - Uses `getConfigSync('max_text_length', 140)` in edit forms
   - Dynamic `maxlength` attribute

4. **backend/public/js/comments-manager.js**
   - Uses `getConfigSync('max_text_length', 140)` throughout
   - Updated in:
     - Comment input creation
     - Character counter updates
     - Edit comment forms
     - Image uploader validation

#### API Documentation
**File:** `backend/public/api-docs.php`
- Updated description from "Max 280 characters" to "Max 140 characters"

### 4. Android App Changes

#### Kotlin Files Updated
1. **android/app/src/main/java/net/kibotu/trail/ui/screens/EntriesScreen.kt**
   - Changed `val maxCharacters = 280` to `140` (2 occurrences)
   - In main screen and edit dialog

2. **android/app/src/main/java/net/kibotu/trail/ui/components/CommentsSection.kt**
   - Changed `val maxCharacters = 280` to `140` (2 occurrences)
   - In comment input and edit dialog

### 5. Database Schema
**No changes required**
- Database uses `VARCHAR(280)` which can still accommodate 140 characters
- Validation is enforced at the application level
- If stricter database constraints are desired, a migration can be created later

## How It Works

### Configuration Flow
1. **Backend:** Reads `max_text_length` from `secrets.yml`
2. **API:** Exposes value via `/api/config` endpoint
3. **Frontend:** Fetches config on page load and caches it
4. **Validation:** Both backend and frontend validate against this limit

### Benefits
- **Single Source of Truth:** All limits come from `secrets.yml`
- **Easy Updates:** Change one value to update everywhere
- **Backward Compatible:** Defaults to 140 if config missing
- **Dynamic:** Frontend adapts without code changes
- **Cached:** Config fetched once per page load

## Testing Checklist

- [ ] Backend validation rejects text > 140 characters
- [ ] Frontend shows correct character counter (X / 140)
- [ ] Frontend disables submit when over limit
- [ ] Edit forms use correct limit
- [ ] Comment forms use correct limit
- [ ] Admin panel uses correct limit
- [ ] Error page uses correct limit
- [ ] Android app enforces 140 character limit
- [ ] API returns correct config value
- [ ] Error messages show correct limit

## Future Enhancements

1. **Dynamic Android Config:** Make Android app fetch config from API
2. **Database Migration:** Optionally reduce VARCHAR(280) to VARCHAR(140)
3. **Admin UI:** Add config management interface
4. **Per-User Limits:** Support different limits for different user types
5. **Validation Rules:** Add to config (min length, allowed characters, etc.)

## Rollback

To revert to 280 characters:
1. Change `max_text_length: 140` to `max_text_length: 280` in `secrets.yml`
2. Restart backend if needed
3. Frontend will automatically pick up the new value
4. Update Android app constants back to 280
