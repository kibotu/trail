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
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import kotlinx.coroutines.launch
import net.kibotu.trail.R
import org.koin.androidx.compose.koinViewModel

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
                val credentialManager = CredentialManager.create(context)
                
                // Get the web client ID from google-services.json
                val googleIdOption = GetGoogleIdOption.Builder()
                    .setFilterByAuthorizedAccounts(false)
                    .setServerClientId("991796147217-iu13ude75qcsue5epgm272rvo28do7lp.apps.googleusercontent.com")
                    .build()
                
                val request = GetCredentialRequest.Builder()
                    .addCredentialOption(googleIdOption)
                    .build()
                
                val result = credentialManager.getCredential(
                    request = request,
                    context = context as Activity
                )
                
                val credential = result.credential
                
                if (credential is GoogleIdTokenCredential) {
                    val googleIdToken = credential.idToken
                    viewModel.authenticateWithGoogle(googleIdToken)
                } else {
                    viewModel.setError("Unexpected credential type")
                }
            } catch (e: GetCredentialCancellationException) {
                // User cancelled the sign-in flow
                viewModel.setError("Sign-in cancelled")
            } catch (e: NoCredentialException) {
                // No Google accounts available
                viewModel.setError("No Google accounts found. Please add a Google account to your device.")
            } catch (e: GetCredentialException) {
                viewModel.setError("Sign-in failed: ${e.message}")
            } catch (e: Exception) {
                viewModel.setError("Unexpected error: ${e.message}")
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
