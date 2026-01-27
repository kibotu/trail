# Trail Android App

A modern Android application for the Trail link journal service, built with Jetpack Compose and Material 3.

## Features

### 1. Authentication
- Google Sign-In integration using Credential Manager API
- JWT token-based authentication
- Secure token storage using DataStore
- Auto-login on app restart if token is valid

### 2. Entries Screen
- **Twitter-style submit form** at the top of the screen
  - 280 character limit with live counter
  - Real-time validation
  - One-tap posting
- **Entries list** showing all user posts
  - User avatars (Gravatar)
  - Formatted timestamps
  - Smooth scrolling with LazyColumn
- Pull-to-refresh functionality
- Logout option

### 3. Share Intent Handling
The app can receive shared content from other Android apps:

#### How it works:
1. **When logged in**: Shared text is automatically posted to your Trail
2. **When logged out**: 
   - App opens to login screen
   - After successful login, the shared text is automatically posted
   - Seamless experience - no need to re-share

#### To test:
1. Open any app with text (Browser, Notes, etc.)
2. Select text and tap "Share"
3. Choose "Trail" from the share menu
4. Text will be posted automatically

### 4. Backend Integration
- RESTful API communication using Retrofit
- JWT token validation on every API call
- Proper error handling and logging
- Network security with HTTPS

## Architecture

### Tech Stack
- **UI**: Jetpack Compose + Material 3
- **Architecture**: MVVM with ViewModel
- **Networking**: Ktor Client (Kotlin-native)
- **Serialization**: Kotlinx Serialization
- **Authentication**: Google Credential Manager
- **Storage**: DataStore (encrypted preferences)
- **Image Loading**: Coil 3
- **Minimum SDK**: 23 (Android 6.0)
- **Target SDK**: 36 (Android 15)

### Project Structure
```
app/src/main/java/net/kibotu/trail/
├── MainActivity.kt                 # Main entry point, handles intents
├── data/
│   ├── api/
│   │   ├── ApiClient.kt           # Retrofit configuration
│   │   └── TrailApi.kt            # API endpoints
│   ├── model/
│   │   ├── Auth.kt                # Auth data models
│   │   └── Entry.kt               # Entry data models
│   └── storage/
│       └── TokenManager.kt        # Secure token storage
├── ui/
│   ├── screens/
│   │   ├── LoginScreen.kt         # Google Sign-In screen
│   │   └── EntriesScreen.kt       # Main entries screen
│   ├── theme/                     # Material 3 theme
│   └── viewmodel/
│       └── TrailViewModel.kt      # App state management
```

## Backend API Verification

The backend properly validates JWT tokens on every API call:

### Token Validation Flow
1. Client sends request with `Authorization: Bearer <token>` header
2. `AuthMiddleware` intercepts the request
3. `JwtService` verifies token signature and expiry
4. If valid, user info is attached to request
5. If invalid, returns 401 Unauthorized

### Protected Endpoints
- `POST /api/entries` - Create new entry (requires auth)
- `GET /api/entries` - List user entries (requires auth)
- `GET /api/admin/*` - Admin endpoints (requires auth + admin role)

## Setup

### Prerequisites
1. Android Studio Ladybug or later
2. JDK 17
3. Google Cloud Console project with OAuth 2.0 configured

### Configuration

1. **Google OAuth Setup**
   - Create `android/local.properties` (if not exists)
   - Add your signing configs
   - Update `android/app/src/main/res/values/strings.xml` with your `default_web_client_id`

2. **Backend URL**
   - Default: `https://trail.services.kibotu.net/`
   - To change: Update `BASE_URL` in `ApiClient.kt`

3. **Build & Run**
   ```bash
   cd android
   ./gradlew assembleDebug
   ./gradlew installDebug
   ```

## Testing Share Intent

### Test from Chrome
1. Open Chrome browser
2. Navigate to any webpage
3. Long-press on text to select it
4. Tap "Share" → "Trail"
5. Text will be posted automatically (after login if needed)

### Test from Notes
1. Open any note-taking app
2. Select text
3. Tap "Share" → "Trail"
4. Text will be posted automatically

## Security Features

1. **JWT Token Validation**: Every API call validates the token
2. **Secure Storage**: Tokens stored in encrypted DataStore
3. **HTTPS Only**: Network security config enforces HTTPS
4. **Token Expiry**: Tokens expire after 7 days (configurable in backend)
5. **No Cleartext Traffic**: App blocks all HTTP connections

## Dependencies

Key libraries used:
- `androidx.credentials:credentials:1.5.0` - Google Sign-In
- `io.ktor:ktor-client-android:3.0.3` - Kotlin-native HTTP client
- `org.jetbrains.kotlinx:kotlinx-serialization-json:1.8.0` - JSON serialization
- `androidx.datastore:datastore-preferences:1.1.2` - Secure storage
- `io.coil-kt.coil3:coil-compose:3.0.4` - Image loading
- `androidx.navigation:navigation-compose:2.9.0` - Navigation
- Material 3 - Modern UI components

## Known Limitations

1. **Android Version**: Credential Manager requires Android 14+ (API 34)
   - For older versions, fallback to legacy Google Sign-In would be needed
2. **Offline Support**: Not implemented yet
3. **Pagination**: Loads first 20 entries only (can be extended)

## Future Enhancements

- [ ] Pull-to-refresh for entries list
- [ ] Infinite scroll / pagination
- [ ] Edit/delete entries
- [ ] Rich text formatting
- [ ] Image attachments
- [ ] Offline support with local caching
- [ ] Push notifications
- [ ] Dark mode toggle
- [ ] Search functionality

## License

See root LICENSE file.
