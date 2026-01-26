package net.kibotu.trail.ui.auth

import android.app.Activity
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.credentials.CredentialManager
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialException
import androidx.credentials.exceptions.NoCredentialException
import com.google.android.libraries.identity.googleid.GetGoogleIdOption
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import kotlinx.coroutines.launch
import kotlinx.coroutines.withTimeout
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.R
import org.koin.androidx.compose.koinViewModel
import timber.log.Timber

@Composable
fun AuthScreen(
    onAuthSuccess: () -> Unit,
    viewModel: AuthViewModel = koinViewModel()
) {
    val authState by viewModel.authState.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    
    LaunchedEffect(authState) {
        if (authState is AuthState.Success) {
            onAuthSuccess()
        }
    }
    
    val handleGoogleSignIn: () -> Unit = {
        scope.launch {
            try {
                Timber.d("Starting Google Sign-In")
                viewModel.setLoading()
                
                val credentialManager = CredentialManager.create(context)
                
                // Use GetSignInWithGoogleOption instead of GetGoogleIdOption
                // This may avoid the AssistedSignInActivity crash with multiple accounts
                val signInWithGoogleOption = GetSignInWithGoogleOption.Builder(BuildConfig.WEB_CLIENT_ID)
                    .build()
                
                val request = GetCredentialRequest.Builder()
                    .addCredentialOption(signInWithGoogleOption)
                    .build()
                
                Timber.d("Requesting credential with 60s timeout")
                
                // Add timeout to prevent hanging
                val result = withTimeout(60000L) { // 60 second timeout
                    credentialManager.getCredential(
                        request = request,
                        context = context as Activity
                    )
                }
                
                Timber.d("Credential received: ${result.credential.type}")
                
                val credential = result.credential
                
                when (credential) {
                    is GoogleIdTokenCredential -> {
                        val googleIdToken = credential.idToken
                        Timber.i("Got Google ID token, authenticating with backend")
                        viewModel.authenticateWithGoogle(googleIdToken)
                    }
                    else -> {
                        Timber.e("Unexpected credential type: ${credential.type}")
                        viewModel.setError("Unexpected credential type: ${credential.type}")
                    }
                }
            } catch (e: GetCredentialCancellationException) {
                Timber.d("User cancelled sign-in")
                viewModel.setError("Sign-in cancelled")
            } catch (e: NoCredentialException) {
                Timber.e(e, "No credentials found")
                viewModel.setError("No Google accounts found. Please add a Google account to your device.")
            } catch (e: GetCredentialException) {
                Timber.e(e, "Credential exception")
                viewModel.setError("Sign-in failed: ${e.message}\n\nTry updating Google Play Services or restarting your device.")
            } catch (e: kotlinx.coroutines.TimeoutCancellationException) {
                Timber.e(e, "Sign-in timeout after 60 seconds")
                viewModel.setError("Sign-in timed out. This may be due to a Google Play Services issue. Try restarting your device.")
            } catch (e: Exception) {
                Timber.e(e, "Unexpected error during sign-in")
                viewModel.setError("Unexpected error: ${e.message}\n\nTry updating Google Play Services.")
            }
        }
    }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Text(
                text = "Trail",
                style = MaterialTheme.typography.displayLarge
            )
            
            Text(
                text = "Share your links with the world",
                style = MaterialTheme.typography.bodyLarge,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(32.dp))
            
            when (authState) {
                is AuthState.Loading -> {
                    CircularProgressIndicator()
                }
                is AuthState.Error -> {
                    Text(
                        text = (authState as AuthState.Error).message,
                        color = MaterialTheme.colorScheme.error,
                        textAlign = TextAlign.Center
                    )
                    
                    Button(
                        onClick = handleGoogleSignIn
                    ) {
                        Text(stringResource(R.string.sign_in_with_google))
                    }
                }
                else -> {
                    Button(
                        onClick = handleGoogleSignIn
                    ) {
                        Text(stringResource(R.string.sign_in_with_google))
                    }
                }
            }
            
            Text(
                text = "Note: Google Sign-In requires google-services.json configuration",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center
            )
        }
    }
}
