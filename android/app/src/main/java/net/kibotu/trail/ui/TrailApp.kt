package net.kibotu.trail.ui

import androidx.compose.runtime.Composable
import net.kibotu.trail.ui.auth.AuthViewModel
import net.kibotu.trail.ui.navigation.NavigationViewModel
import net.kibotu.trail.ui.navigation.TrailNavHost
import org.koin.androidx.compose.koinViewModel

/**
 * Root composable for the Trail app using Navigation 3.
 * 
 * Navigation 3 differences from Navigation 2:
 * - No NavController - uses direct back stack management
 * - NavigationViewModel manages the back stack as mutableStateListOf
 * - Simpler and more direct navigation API
 * 
 * Follows unidirectional data flow principles:
 * - Navigation state is managed by NavigationViewModel
 * - ViewModels emit state via StateFlow
 * - UI observes state and emits events upward
 * - Navigation decisions are centralized in TrailNavHost
 *
 * @param sharedUrl Optional URL shared from external sources (e.g., share intent)
 * @param authViewModel ViewModel for authentication state (hoisted for testing)
 * @param navigationViewModel ViewModel for navigation back stack (hoisted for testing)
 */
@Composable
fun TrailApp(
    sharedUrl: String? = null,
    authViewModel: AuthViewModel = koinViewModel(),
    navigationViewModel: NavigationViewModel = koinViewModel()
) {
    TrailNavHost(
        sharedUrl = sharedUrl,
        authViewModel = authViewModel,
        navigationViewModel = navigationViewModel
    )
}
