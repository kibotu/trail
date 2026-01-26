# Trail

Link sharing app with Android client and PHP backend.

## Quick Start

```bash
cd android
./gradlew clean assembleDebug installDebug
```

## Google Sign-In Setup

**Status**: Configured with Credential Manager API (androidx.credentials 1.5.0)

### Requirements

- Google Play Services 24.40.XX+ (for Android 14+ compatibility)
- Android SDK 23+ (target SDK 36)

### OAuth Clients

- **Debug**: `991796147217-d1au746ig4tot7flidbg5fb3croaf2tq`
- **Release**: `991796147217-qasmipups3pp58raeu2ue8tk67jkob5j`
- **Web**: `991796147217-iu13ude75qcsue5epgm272rvo28do7lp` (backend validation)

### Configuration

#### 1. Get SHA-1 Fingerprint

```bash
cd android
./get-sha-fingerprints.sh
```

#### 2. Create Android OAuth Client

1. [Google Cloud Console - Credentials](https://console.cloud.google.com/apis/credentials)
2. Create Credentials → OAuth client ID → Android
3. Package name: `net.kibotu.trail`
4. SHA-1: Your fingerprint from step 1
5. Create

#### 3. Update Firebase Config

1. [Firebase Console](https://console.firebase.google.com) → Project Settings
2. Download `google-services.json`
3. Replace:
   - `android/app/google-services.json`
   - `.secrets/google-services.json`

Verify both OAuth clients exist:
```json
"oauth_client": [
  {"client_type": 1, "android_info": {...}},  // Android
  {"client_type": 3}                          // Web
]
```

#### 4. Verify Setup

```bash
cd android
./diagnose-google-signin.sh
```

## Troubleshooting

### Bottom Sheet Crashes (Android 14+)

**Symptom**: "width and height must be > 0"

**Fix**: Update Google Play Services to 24.40.XX+

```bash
# Check version
adb shell dumpsys package com.google.android.gms | grep versionName

# Clear cache if needed
cd android
./fix-google-play-services.sh
```

### Common Errors

| Error | Cause | Fix |
|-------|-------|-----|
| No accounts found | Missing Android OAuth client | Run `diagnose-google-signin.sh` |
| Error 16 | SHA-1 mismatch | Verify fingerprint in Google Cloud |
| Backend auth fails | Wrong Web Client ID | Check backend uses correct ID |

## Android 16 Edge-to-Edge

**Status**: Temporarily disabled due to Google Play Services bug

Edge-to-edge rendering is disabled in `MainActivity.kt` until Google fixes compatibility issues. The app uses traditional system bars.

**Re-enable when fixed**:
```bash
./scripts/re_enable_edge_to_edge.sh
```

Monitor these libraries for updates:
- `androidx.credentials:credentials`
- `androidx.credentials:credentials-play-services-auth`
- `com.google.android.libraries.identity.googleid:googleid`

## Project Structure

```
trail/
├── android/              # Kotlin + Compose
│   ├── app/
│   │   └── google-services.json
│   ├── get-sha-fingerprints.sh
│   └── diagnose-google-signin.sh
├── backend/              # PHP
├── .secrets/             # Not in git
│   └── google-services.json
└── README.md
```

## Development

### Android

```bash
cd android
./gradlew assembleDebug
./gradlew installDebug
```

### Backend

```bash
cd backend
./run.sh
```

Available at `http://localhost:18000`

## Documentation

- [Credential Manager API](https://developers.google.com/identity/android-credential-manager)
- [Troubleshooting Guide](https://developer.android.com/identity/sign-in/credential-manager-troubleshooting-guide)
- [Firebase Console](https://console.firebase.google.com/project/kibotu-trail)
- [Google Cloud Console](https://console.cloud.google.com/apis/credentials?project=kibotu-trail)
