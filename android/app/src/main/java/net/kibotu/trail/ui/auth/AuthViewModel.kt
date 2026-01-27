package net.kibotu.trail.ui.auth

import androidx.credentials.CredentialManager
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialException
import androidx.credentials.exceptions.NoCredentialException
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.github.florent37.application.provider.application
import com.google.android.libraries.identity.googleid.GetGoogleIdOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.google.firebase.Firebase
import com.google.firebase.crashlytics.crashlytics
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withTimeout
import net.kibotu.resourceextension.resBoolean
import net.kibotu.trail.R
import net.kibotu.trail.data.model.AuthResponse
import net.kibotu.trail.data.model.User
import net.kibotu.trail.data.repository.TrailRepository
import timber.log.Timber

sealed class AuthState {
    data object Initial : AuthState()
    data object Loading : AuthState()
    data class Success(val authResponse: AuthResponse) : AuthState()
    data class Error(val message: String) : AuthState()
}

class AuthViewModel(private val repository: TrailRepository) : ViewModel() {

    private val _authState = MutableStateFlow<AuthState>(AuthState.Initial)
    val authState: StateFlow<AuthState> = _authState.asStateFlow()

    val isLoggedIn = repository.isLoggedIn

    init {
//        // Auto-login in developer mode if enabled
//        if (R.bool.skip_auth_in_dev.resBoolean) {
//            Timber.d("Developer mode: skipping authentication")
//            skipAuthForDevelopment()
//        }
    }

    private fun skipAuthForDevelopment() {
        viewModelScope.launch {
            _authState.value = AuthState.Loading
            
            // Create a mock auth response for development
            val mockAuthResponse = AuthResponse(
                jwt = "dev-token-${System.currentTimeMillis()}",
                user = User(
                    id = 1,
                    email = "dev@example.com",
                    name = "Developer",
                    gravatarUrl = "https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y"
                )
            )
            
            val result = repository.saveDevAuthData(mockAuthResponse)
            
            _authState.value = if (result.isSuccess) {
                Timber.i("Developer mode authentication successful")
                AuthState.Success(mockAuthResponse)
            } else {
                Timber.e("Developer mode authentication failed")
                AuthState.Error("Developer mode setup failed")
            }
        }
    }

    fun authenticateWithGoogle(googleToken: String) {
        viewModelScope.launch {
            _authState.value = AuthState.Loading

            val result = repository.authenticateWithGoogle(googleToken)

            _authState.value = if (result.isSuccess) {
                val authResponse = result.getOrNull()!!
                Firebase.crashlytics.setUserId(authResponse.user.id.toString())
                AuthState.Success(authResponse)
            } else {
                val error = result.exceptionOrNull()
                Firebase.crashlytics.recordException(error ?: Exception("Unknown auth error"))
                AuthState.Error(error?.message ?: "Authentication failed")
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            repository.logout()
            Firebase.crashlytics.setUserId("")
            _authState.value = AuthState.Initial
        }
    }

    fun login() {
        val context = application ?: return
//
//        // Skip real auth in developer mode
//        if (R.bool.skip_auth_in_dev.resBoolean) {
//            Timber.d("Developer mode: skipping real authentication")
//            skipAuthForDevelopment()
//            return
//        }
        
        viewModelScope.launch {
            try {
                Timber.d("Starting Google Sign-In")
                _authState.value = AuthState.Loading

                val credentialManager = CredentialManager.create(context)

                val googleIdOption = GetGoogleIdOption.Builder()
                    .setFilterByAuthorizedAccounts(false)
                    .setServerClientId(context.getString(R.string.google_oauth_2_client))
                    .build()

                val request = GetCredentialRequest.Builder()
                    .addCredentialOption(googleIdOption)
                    .build()

                Timber.d("Requesting credential with 60s timeout")

                val result = withTimeout(60000L) {
                    credentialManager.getCredential(
                        request = request,
                        context = context
                    )
                }

                Timber.d("Credential received: ${result.credential.type}")

                val credential = result.credential

                if (credential is GoogleIdTokenCredential) {
                    val googleIdToken = credential.idToken
                    Timber.i("Got Google ID token, authenticating with backend")
                    authenticateWithGoogle(googleIdToken)
                } else {
                    Timber.e("Unexpected credential type: ${credential.type}")
                    _authState.value = AuthState.Error("Unexpected credential type: ${credential.type}")
                }
            } catch (e: GetCredentialCancellationException) {
                Timber.d("User cancelled sign-in")
                _authState.value = AuthState.Error("Sign-in cancelled")
            } catch (e: NoCredentialException) {
                Timber.e(e, "No credentials found")
                _authState.value = AuthState.Error("No Google accounts found. Please add a Google account to your device.")
            } catch (e: GetCredentialException) {
                Timber.e(e, "Credential exception")
                _authState.value = AuthState.Error("Sign-in failed: ${e.message}\n\nTry updating Google Play Services or restarting your device.")
            } catch (e: kotlinx.coroutines.TimeoutCancellationException) {
                Timber.e(e, "Sign-in timeout after 60 seconds")
                _authState.value = AuthState.Error("Sign-in timed out. This may be due to a Google Play Services issue. Try restarting your device.")
            } catch (e: Exception) {
                Timber.e(e, "Unexpected error during sign-in")
                _authState.value = AuthState.Error("Unexpected error: ${e.message}\n\nTry updating Google Play Services.")
            }
        }
    }
}

