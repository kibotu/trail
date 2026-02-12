# Trail for Android

Android client for [Trail](https://github.com/kibotu/trail), the self-hosted micro link journal.

## Build

### Prerequisites

- Android Studio Ladybug+ or command-line SDK tools
- JDK 17
- Google Cloud project with OAuth 2.0 Web Client ID

### Configure

Create `android/local.properties`:

```properties
# Required: Your backend URL (no trailing slash issues, Ktor handles it)
API_BASE_URL=https://your-trail-instance.example.com/

# Required: Google OAuth Web Client ID (from Cloud Console → Credentials)
# Also update app/src/main/res/values/strings.xml → default_web_client_id

# Debug signing (generate with: keytool -genkey -v -keystore debug.jks -alias debug -keyalg RSA -keysize 2048 -validity 10000)
DEBUG_KEYSTORE_PATH=certificates/debug.jks
DEBUG_STORE_PASSWORD=your_password
DEBUG_KEYSTORE_ALIAS=debug
DEBUG_KEY_PASSWORD=your_password

# Release signing
RELEASE_KEYSTORE_PATH=certificates/release.jks
RELEASE_STORE_PASSWORD=your_password
RELEASE_KEYSTORE_ALIAS=release
RELEASE_KEY_PASSWORD=your_password
```

### Build & Install

```bash
cd android

# Debug
./gradlew assembleDebug
./gradlew installDebug

# Release (minified, R8)
./gradlew assembleRelease
```

APKs land in `app/build/outputs/apk/`.

## Features

- **Share intent** — Post from any app. Select text → Share → Trail. Works pre-login (queues until authenticated).
- **Dual feeds** — Global chronological timeline + your personal feed at `/@username`
- **Search** — Full-text search with debounced overlay, infinite scroll
- **URL previews** — Automatic card extraction via Iframely (title, description, image, site name)
- **Media** — Images, GIFs (Coil), video playback (ExoPlayer) with inline/fullscreen, mute, one-video-at-a-time
- **Comments** — Threaded comments with claps (1-50) and reporting
- **Google Sign-In** — Credential Manager API, JWT auth, auto-login on restart
- **Profile** — Avatar, bio (160 chars), nickname, stats (entries, comments, links, views, claps)
- **Theme** — Light/dark toggle, persisted in SharedPreferences

## Architecture

Single-activity Compose app. MVVM with a single `TrailViewModel` managing UI state via `StateFlow`. No DI framework — manual wiring. Ktor for networking, DataStore for token persistence.

```
app/src/main/java/net/kibotu/trail/
├── MainActivity.kt              # Entry point, share intent handling
├── data/
│   ├── api/
│   │   ├── ApiClient.kt         # Ktor HttpClient config
│   │   └── TrailApi.kt          # API endpoints
│   ├── model/
│   │   ├── Auth.kt
│   │   ├── Comment.kt
│   │   ├── Entry.kt
│   │   ├── MediaItemData.kt
│   │   └── Profile.kt
│   └── storage/
│       ├── ThemePreferences.kt
│       └── TokenManager.kt      # DataStore token persistence
├── extensions/
│   └── Activity+Extensions.kt
└── ui/
    ├── components/
    │   ├── CommentsSection.kt
    │   ├── EntryComponents.kt
    │   ├── FloatingTabBar.kt
    │   ├── MediaComponents.kt   # Image/GIF/Video composables
    │   ├── SearchFab.kt
    │   ├── SearchOverlay.kt
    │   └── VideoPlaybackManager.kt
    ├── navigation/
    │   └── TrailNavigation.kt   # Tab routes
    ├── screens/
    │   ├── HomeScreen.kt        # Global feed
    │   ├── LoginScreen.kt
    │   ├── MyFeedScreen.kt      # User's entries
    │   └── ProfileScreen.kt
    ├── theme/
    │   ├── Color.kt
    │   ├── Theme.kt
    │   └── Type.kt
    └── viewmodel/
        └── TrailViewModel.kt
```

## Stack

| Category | Library | Version |
|----------|---------|---------|
| Language | Kotlin | 2.3.10 |
| UI | Jetpack Compose (BOM) | 2026.02.00 |
| Design | Material 3 | — |
| Network | Ktor Client | 3.4.0 |
| Serialization | kotlinx-serialization | 1.10.0 |
| Images | Coil 3 | 3.3.0 |
| Video | Media3 ExoPlayer | 1.6.1 |
| Auth | Credential Manager | 1.5.0 |
| Storage | DataStore Preferences | 1.2.0 |
| Navigation | Navigation Compose | 2.9.7 |
| Build | Gradle | 9.3.1 |
| Build | AGP | 9.1.0-alpha08 |
| JDK | | 17 |
| SDK | minSdk | 23 |
| SDK | targetSdk | 36 |

## API Endpoints

The app uses these backend endpoints:

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/auth/google` | Exchange Google ID token for JWT |
| GET | `/api/entries` | Global feed (supports `q`, `limit`, `before`) |
| POST | `/api/entries` | Create entry |
| PUT | `/api/entries/{id}` | Update entry |
| DELETE | `/api/entries/{id}` | Delete entry |
| GET | `/api/users/{nickname}/entries` | User feed |
| GET | `/api/entries/{id}/comments` | List comments |
| POST | `/api/entries/{id}/comments` | Create comment |
| PUT | `/api/comments/{id}` | Update comment |
| DELETE | `/api/comments/{id}` | Delete comment |
| POST | `/api/comments/{id}/claps` | Add claps |
| POST | `/api/comments/{id}/report` | Report comment |
| GET | `/api/profile` | Current user profile |
| PUT | `/api/profile` | Update profile |

## Security

- **HTTPS only** — `networkSecurityConfig` blocks cleartext traffic
- **JWT auth** — Bearer token on all authenticated requests
- **Encrypted storage** — Tokens in DataStore (encrypted SharedPreferences)
- **R8/ProGuard** — Release builds minified and obfuscated
- **No backup** — `allowBackup=false` prevents token extraction

## License

Apache 2.0 — see root [LICENSE](../LICENSE).
