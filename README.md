# Trail

A link sharing app with Android client and PHP backend.

## Google Sign-In Setup

✅ **Status**: Configured with latest Credential Manager API (2026)

The Android app uses Google Sign-In via the **Credential Manager API** (androidx.credentials 1.5.0) with `GetSignInWithGoogleOption` for optimal compatibility.

### OAuth Clients Configured

- **Debug**: `991796147217-d1au746ig4tot7flidbg5fb3croaf2tq` (Android, SHA-1: 79:16:DA:...)
- **Release**: `991796147217-qasmipups3pp58raeu2ue8tk67jkob5j` (Android, SHA-1: A5:FE:33:...)
- **Web**: `991796147217-iu13ude75qcsue5epgm272rvo28do7lp` (Backend token validation)

### Quick Start

```bash
cd android
./gradlew clean assembleDebug installDebug
```

### Requirements

- **Google Play Services**: Version 24.40.XX or higher (for Android 14+ compatibility)
- **Android SDK**: API 23+ (Android 6.0+)
- **Target SDK**: API 36 (Android 15)

### Setup Steps (For New Environments)

#### 1. Get Your SHA-1 Fingerprint

```bash
cd android
keytool -list -v -keystore app/certificates/debug.jks -alias debug -storepass YOUR_PASSWORD | grep SHA1
```

Or use the helper script:
```bash
cd android
./get-sha-fingerprints.sh
```

#### 2. Configure Google Cloud Console

**Create Android OAuth Client:**

1. Go to [Google Cloud Console - Credentials](https://console.cloud.google.com/apis/credentials)
2. Click **"Create Credentials"** → **"OAuth client ID"**
3. Select **Application type: Android**
4. Fill in:
   - **Name**: `Trail Android App` (or any name)
   - **Package name**: `net.kibotu.trail`
   - **SHA-1 certificate fingerprint**: Your SHA-1 from step 1
5. Click **"Create"**

**Important**: You need BOTH OAuth clients:
- ✅ **Android OAuth client** (type 1) - for device authentication
- ✅ **Web OAuth client** (type 3) - for backend token validation

📚 [Google Cloud OAuth Documentation](https://cloud.google.com/docs/authentication/api-keys)

#### 3. Configure Firebase

1. Go to [Firebase Console](https://console.firebase.google.com)
2. Select your project → **Project Settings** → **General**
3. Scroll to **"Your apps"** → Find your Android app
4. Click the **gear icon** → **"Download google-services.json"**
5. Replace these files with the downloaded file:
   ```
   android/app/google-services.json
   .secrets/google-services.json
   ```

**Verify the configuration:**
The `google-services.json` should contain both OAuth clients:
```json
"oauth_client": [
  {
    "client_id": "...",
    "client_type": 1,  // Android client
    "android_info": {
      "package_name": "net.kibotu.trail",
      "certificate_hash": "..."
    }
  },
  {
    "client_id": "...",
    "client_type": 3  // Web client
  }
]
```

📚 [Firebase Android Setup](https://firebase.google.com/docs/android/setup)  
📚 [Google Sign-In with Credential Manager](https://firebase.google.com/docs/auth/android/google-signin)

#### 4. Build and Test

```bash
cd android
./gradlew clean assembleDebug installDebug
```

**Expected behavior:**
1. Tap "Sign in with Google"
2. Google account picker appears
3. Select account
4. Sign in succeeds

### Verify Configuration

Run diagnostics to check your setup:
```bash
cd android
./diagnose-google-signin.sh
```

Expected output:
```
✓ Android OAuth client (type 1) found
✓ Package name is correct
✓ SHA-1 fingerprint matches
✓ Configuration looks good!
```

### Troubleshooting

**Bottom sheet crashes or hangs:**
- **Cause**: Known Google Play Services bug on Android 14+ with multiple accounts
- **Solution**: Update Google Play Services to version 24.40.XX or higher
- **Check version**: `adb shell dumpsys package com.google.android.gms | grep versionName`
- **Fix**: Open Play Store → Search "Google Play services" → Update

**"No Google accounts found" error:**
- Missing Android OAuth client in Google Cloud Console
- SHA-1 fingerprint mismatch
- Run diagnostics: `cd android && ./diagnose-google-signin.sh`

**"Sign-in failed: 16" or "Developer error":**
- SHA-1 fingerprint doesn't match in Google Cloud Console
- Package name mismatch (must be `net.kibotu.trail`)
- Verify configuration in Google Cloud Console

**"GetCredentialResponse error":**
- `google-services.json` not updated after creating OAuth client
- Re-download from Firebase Console and replace both copies:
  - `android/app/google-services.json`
  - `.secrets/google-services.json`

**Backend authentication fails:**
- Ensure backend uses Web Client ID: `991796147217-iu13ude75qcsue5epgm272rvo28do7lp`
- Verify ID token validation is configured correctly

**Clear Google Play Services cache:**
```bash
cd android
./fix-google-play-services.sh
```

📚 **Documentation:**
- [Credential Manager API](https://developers.google.com/identity/android-credential-manager)
- [Official Troubleshooting Guide](https://developer.android.com/identity/sign-in/credential-manager-troubleshooting-guide)
- [Sign in with Google Integration](https://developer.android.com/training/sign-in/credential-manager)

### Additional Resources

- [Credential Manager Documentation](https://developer.android.com/training/sign-in/credential-manager)
- [Google Sign-In Integration Guide](https://developer.android.com/identity/sign-in/credential-manager-siwg)
- [Firebase Authentication](https://firebase.google.com/docs/auth)

## Backend Setup

See `backend/README.md` for backend configuration.

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

The backend will be available at `http://localhost:18000`

## Project Structure

```
trail/
├── android/              # Android app (Kotlin + Compose)
│   ├── app/
│   │   └── google-services.json
│   ├── get-sha-fingerprints.sh
│   └── diagnose-google-signin.sh
├── backend/              # PHP backend
├── .secrets/             # Secret files (not in git)
│   └── google-services.json
└── README.md
```

## License

[Your License Here]
