# Trail - Share Links with the World

A modern Android app built with Jetpack Compose for sharing and managing links.

## Features

- 🔐 Google Sign-In authentication
- 📝 Share links with text (max 280 characters)
- 📱 Beautiful Material 3 UI
- 🚀 Modern architecture with unidirectional data flow
- 🧭 Type-safe navigation with Navigation 3
- 🛠️ Developer mode for faster iteration

## Architecture

Trail follows modern Android development best practices:

### Tech Stack

- **UI**: Jetpack Compose with Material 3
- **Navigation**: Navigation 3 (latest generation)
- **Architecture**: MVVM with UDF (Unidirectional Data Flow)
- **DI**: Koin
- **Networking**: Ktor Client
- **State Management**: Kotlin StateFlow
- **Serialization**: Kotlin Serialization
- **Authentication**: Google Credential Manager
- **Analytics**: Firebase Crashlytics

### Key Libraries

```kotlin
// Compose
implementation("androidx.compose:compose-bom:2026.01.00")
implementation("androidx.compose.material3:material3")

// Navigation 3
implementation("androidx.navigation3:navigation3-runtime:1.0.0")
implementation("androidx.navigation3:navigation3-ui:1.0.0")

// Ktor Client
implementation("io.ktor:ktor-client-android:3.4.0")

// Koin
implementation("io.insert-koin:koin-androidx-compose:4.1.1")

// Firebase
implementation("com.google.firebase:firebase-crashlytics")
```

## Project Structure

```
app/
├── ui/
│   ├── auth/          # Authentication screen & ViewModel
│   ├── list/          # Entry list screen & ViewModel
│   ├── share/         # Share screen & ViewModel
│   ├── navigation/    # Navigation 3 setup
│   └── theme/         # Material 3 theme
├── data/
│   ├── api/           # API service
│   ├── model/         # Data models
│   └── repository/    # Repository pattern
└── di/                # Dependency injection
```

## Navigation 3

Trail uses **Navigation 3**, the next generation of Android navigation:

### Key Features

- ✅ Direct back stack management
- ✅ Type-safe navigation with Kotlin Serialization
- ✅ No NavController needed
- ✅ Pass complex objects directly
- ✅ Simpler API

### Example

```kotlin
// Define destinations
sealed interface Screen : NavKey {
    @Serializable
    data object Auth : Screen
    
    @Serializable
    data class Share(val url: String) : Screen
}

// Navigate
navigationViewModel.navigate(Screen.Share(url = "https://example.com"))

// Back stack is directly accessible
val currentScreen = navigationViewModel.backStack.last()
```

See [NAVIGATION_3_MIGRATION.md](NAVIGATION_3_MIGRATION.md) for details.

## Developer Mode

Trail includes a developer mode to skip authentication during development:

### Enable Developer Mode

In `src/debug/res/values/config.xml`:
```xml
<bool name="skip_auth_in_dev">true</bool>
```

When enabled:
- Authentication is automatically bypassed
- Mock user credentials are used
- Faster development iteration
- No need for Google OAuth setup

### Disable for Production

In `src/main/res/values/config.xml`:
```xml
<bool name="skip_auth_in_dev">false</bool>
```

## Building the App

### Prerequisites

- Android Studio Ladybug or later
- JDK 17
- Android SDK 36
- Kotlin 2.3.0

### Build Commands

```bash
# Debug build (with developer mode)
./gradlew assembleDebug

# Release build
./gradlew assembleRelease

# Run tests
./gradlew test

# Run instrumented tests
./gradlew connectedAndroidTest
```

## Configuration

### Google OAuth Setup

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/)
2. Enable Google Sign-In API
3. Create OAuth 2.0 credentials
4. Add SHA-1 fingerprint of your signing key
5. Update `google_oauth_2_client` in `config.xml`

### Firebase Setup

1. Create a project in [Firebase Console](https://console.firebase.google.com/)
2. Add Android app
3. Download `google-services.json`
4. Place in `app/` directory
5. Enable Crashlytics

## Testing

Trail includes comprehensive tests:

### Unit Tests

```bash
./gradlew test
```

Tests include:
- ViewModel logic
- Repository operations
- Navigation flows
- State management

### UI Tests

```bash
./gradlew connectedAndroidTest
```

Tests include:
- Screen rendering
- User interactions
- Navigation transitions
- Error states

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) - Architecture overview and patterns
- [NAVIGATION_3_MIGRATION.md](NAVIGATION_3_MIGRATION.md) - Navigation 3 migration guide
- [NAVIGATION_GUIDE.md](NAVIGATION_GUIDE.md) - Navigation patterns and best practices
- [CHANGES.md](CHANGES.md) - Detailed changelog

## Code Style

Trail follows idiomatic Kotlin and Jetpack Compose best practices:

### Principles

- **Idiomatic**: Modern Kotlin patterns (sealed interfaces, data objects)
- **Pragmatic**: Practical solutions over theoretical purity
- **Excellent**: High-quality, production-ready code
- **Concise**: Clear and minimal code
- **Positive**: Optimistic error handling
- **Humble**: Documented trade-offs and improvements

### Examples

```kotlin
// ✅ Idiomatic Kotlin
sealed interface State {
    data object Loading : State
    data class Success(val data: Data) : State
}

// ✅ Unidirectional Data Flow
class MyViewModel : ViewModel() {
    private val _state = MutableStateFlow<State>(State.Loading)
    val state: StateFlow<State> = _state.asStateFlow()
    
    fun onEvent(event: Event) {
        viewModelScope.launch {
            // Handle event
        }
    }
}

// ✅ Type-safe Navigation
navigationViewModel.navigate(Screen.Detail(item))
```

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Follow the existing code style
2. Write tests for new features
3. Update documentation
4. Use conventional commits
5. Keep PRs focused and small

## License

Copyright 2026 Kibotu

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

## Resources

### Official Documentation
- [Jetpack Compose](https://developer.android.com/jetpack/compose)
- [Navigation 3](https://developer.android.com/guide/navigation/navigation-3)
- [Architecture Guide](https://developer.android.com/topic/architecture)
- [Kotlin Coroutines](https://kotlinlang.org/docs/coroutines-overview.html)

### Sample Apps
- [Now in Android](https://github.com/android/nowinandroid) - Google's official sample
- [Navigation 3 Recipes](https://github.com/android/nav3-recipes) - Navigation 3 examples

## Contact

For questions or feedback:
- Create an issue on GitHub
- Email: [your-email@example.com]

---

Built with ❤️ using Jetpack Compose and Navigation 3
