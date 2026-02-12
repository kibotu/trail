package net.kibotu.trail.ui.screens

import android.annotation.SuppressLint
import android.content.Context
import android.os.Build
import android.util.Log
import android.widget.Toast
import androidx.annotation.RequiresApi
import androidx.compose.foundation.Image
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.credentials.CredentialManager
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialCustomException
import androidx.credentials.exceptions.NoCredentialException
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.google.android.libraries.identity.googleid.GoogleIdTokenParsingException
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import net.kibotu.trail.R
import java.security.SecureRandom
import java.util.Base64

@RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
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
        Column(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            if (isLoading) {
                CircularProgressIndicator()
            } else {
                Image(
                    painter = painterResource(id = R.drawable.android_light_rd_si),
                    contentDescription = "Sign in with Google",
                    modifier = Modifier
                        .fillMaxWidth(0.8f)
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

@SuppressLint("NewApi")
private fun generateSecureRandomNonce(byteLength: Int = 32): String {
    val randomBytes = ByteArray(byteLength)
    SecureRandom.getInstanceStrong().nextBytes(randomBytes)
    return Base64.getUrlEncoder().withoutPadding().encodeToString(randomBytes)
}

@RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
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
        Log.i("LoginScreen", "Sign in successful!")
        Toast.makeText(context, "Sign in successful!", Toast.LENGTH_SHORT).show()

        credential.idToken
    } catch (e: Exception) {
        when (e) {
            is GetCredentialCancellationException -> {
                Log.e("LoginScreen", "Sign-in was cancelled", e)
                Toast.makeText(context, "Sign-in cancelled", Toast.LENGTH_SHORT).show()
            }

            is NoCredentialException -> {
                Log.e("LoginScreen", "No credentials found", e)
                Toast.makeText(context, failureMessage, Toast.LENGTH_SHORT).show()
            }

            is GetCredentialCustomException -> {
                Log.e("LoginScreen", "Custom credential request issue", e)
                Toast.makeText(context, failureMessage, Toast.LENGTH_SHORT).show()
            }

            is GoogleIdTokenParsingException -> {
                Log.e("LoginScreen", "Issue with parsing GoogleIdToken", e)
                Toast.makeText(context, failureMessage, Toast.LENGTH_SHORT).show()
            }

            else -> {
                Log.e("LoginScreen", "Failure getting credentials", e)
                Toast.makeText(context, failureMessage, Toast.LENGTH_SHORT).show()
            }
        }
        null
    }
}
