package net.kibotu.trail.ui

import androidx.compose.runtime.*
import net.kibotu.trail.ui.auth.AuthScreen
import net.kibotu.trail.ui.auth.AuthViewModel
import net.kibotu.trail.ui.list.EntryListScreen
import net.kibotu.trail.ui.share.ShareScreen
import org.koin.androidx.compose.koinViewModel

sealed class Destination {
    object Auth : Destination()
    data class Share(val sharedUrl: String?) : Destination()
    object EntryList : Destination()
}

@Composable
fun TrailApp(
    sharedUrl: String?,
    authViewModel: AuthViewModel = koinViewModel()
) {
    val isLoggedIn by authViewModel.isLoggedIn.collectAsState(initial = false)
    
    var currentDestination by remember {
        mutableStateOf<Destination>(
            if (sharedUrl != null) Destination.Share(sharedUrl) else Destination.Auth
        )
    }
    
    LaunchedEffect(isLoggedIn) {
        if (!isLoggedIn && currentDestination != Destination.Auth) {
            currentDestination = Destination.Auth
        }
    }
    
    when (val destination = currentDestination) {
        is Destination.Auth -> {
            AuthScreen(
                onAuthSuccess = {
                    currentDestination = if (sharedUrl != null) {
                        Destination.Share(sharedUrl)
                    } else {
                        Destination.EntryList
                    }
                }
            )
        }
        is Destination.Share -> {
            ShareScreen(
                sharedUrl = destination.sharedUrl,
                onSuccess = {
                    currentDestination = Destination.EntryList
                },
                onCancel = {
                    currentDestination = Destination.EntryList
                }
            )
        }
        is Destination.EntryList -> {
            EntryListScreen(
                onSignOut = {
                    authViewModel.logout()
                    currentDestination = Destination.Auth
                }
            )
        }
    }
}
