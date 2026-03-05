package net.kibotu.trail.feature.auth

import android.content.Context
import android.widget.Toast
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.credentials.CredentialManager
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialCustomException
import androidx.credentials.exceptions.NoCredentialException
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import coil3.compose.AsyncImage
import com.google.android.libraries.identity.googleid.GoogleIdTokenParsingException
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.R
import timber.log.Timber
import java.security.SecureRandom

@Composable
fun LoginScreen(
    onLoginSuccess: (String) -> Unit,
    isLoading: Boolean = false
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val webClientId = stringResource(R.string.google_auth_web_client)

    Surface(
        modifier = Modifier.fillMaxSize(),
        color = MaterialTheme.colorScheme.background
    ) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            Column(
                modifier = Modifier.widthIn(max = 400.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                if (isLoading) {
                    CircularProgressIndicator()
                } else {
                    AsyncImage(
                        model = "${BuildConfig.API_BASE_URL}assets/login-whale.png",
                        contentDescription = "Log in with Google",
                        contentScale = ContentScale.FillWidth,
                        modifier = Modifier
                            .fillMaxWidth(0.8f)
                            .aspectRatio(1f)
                            .clickable {
                                coroutineScope.launch {
                                    val idToken = performGoogleSignIn(context, webClientId)
                                    idToken?.let { onLoginSuccess(it) }
                                }
                            }
                    )
                }
            }
        }
    }
}

private fun generateSecureRandomNonce(byteLength: Int = 32): String {
    val randomBytes = ByteArray(byteLength)
    SecureRandom().nextBytes(randomBytes)
    return android.util.Base64.encodeToString(
        randomBytes,
        android.util.Base64.URL_SAFE or android.util.Base64.NO_PADDING or android.util.Base64.NO_WRAP
    )
}

private suspend fun performGoogleSignIn(context: Context, webClientId: String): String? {
    val credentialManager = CredentialManager.create(context)
    val failureMessage = "Sign in failed!"

    val signInWithGoogleOption = GetSignInWithGoogleOption
        .Builder(serverClientId = webClientId)
        .setNonce(generateSecureRandomNonce())
        .build()

    val request = GetCredentialRequest.Builder()
        .addCredentialOption(signInWithGoogleOption)
        .build()

    delay(250)

    return try {
        val result = credentialManager.getCredential(
            request = request,
            context = context,
        )

        val credential = GoogleIdTokenCredential.createFrom(result.credential.data)
        Timber.i("Sign in successful!")
        Toast.makeText(context, "Sign in successful!", Toast.LENGTH_SHORT).show()

        credential.idToken
    } catch (e: Exception) {
        when (e) {
            is GetCredentialCancellationException -> {
                Timber.e(e, "Sign-in was cancelled")
                Toast.makeText(context, "Sign-in cancelled", Toast.LENGTH_SHORT).show()
            }
            is NoCredentialException -> {
                Timber.e(e, "No credentials found")
                Toast.makeText(context, failureMessage, Toast.LENGTH_SHORT).show()
            }
            is GetCredentialCustomException -> {
                Timber.e(e, "Custom credential request issue")
                Toast.makeText(context, failureMessage, Toast.LENGTH_SHORT).show()
            }
            is GoogleIdTokenParsingException -> {
                Timber.e(e, "Issue with parsing GoogleIdToken")
                Toast.makeText(context, failureMessage, Toast.LENGTH_SHORT).show()
            }
            else -> {
                Timber.e(e, "Failure getting credentials")
                Toast.makeText(context, failureMessage, Toast.LENGTH_SHORT).show()
            }
        }
        null
    }
}
