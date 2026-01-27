package net.kibotu.trail.ui.navigation

import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.lifecycle.ViewModel
import androidx.navigation3.runtime.NavKey
import androidx.navigation3.ui.NavDisplay
import androidx.navigation3.runtime.rememberSaveableStateHolderNavEntryDecorator
import kotlinx.serialization.Serializable
import net.kibotu.trail.ui.auth.AuthViewModel
import org.koin.androidx.compose.koinViewModel
import org.koin.compose.navigation3.koinEntryProvider
import org.koin.core.annotation.KoinExperimentalAPI

/**
 * Type-safe navigation destinations using sealed interface with NavKey.
 * Navigation 3 uses Kotlin Serialization for type-safe navigation.
 */
sealed interface Screen : NavKey {
    @Serializable
    data object Auth : Screen
    
    @Serializable
    data object EntryList : Screen
    
    @Serializable
    data class Share(val sharedUrl: String? = null) : Screen
}

/**
 * ViewModel to manage Navigation 3 back stack.
 * In Navigation 3, we directly manage the back stack as a mutable list.
 */
class NavigationViewModel : ViewModel() {
    val backStack = mutableStateListOf<Screen>(Screen.Auth)
    
    fun navigate(screen: Screen) {
        backStack.add(screen)
    }
    
    fun navigateBack() {
        if (backStack.size > 1) {
            backStack.removeLastOrNull()
        }
    }
    
    fun navigateAndClearBackStack(screen: Screen) {
        backStack.clear()
        backStack.add(screen)
    }
    
    fun replaceWith(screen: Screen) {
        backStack.removeLastOrNull()
        backStack.add(screen)
    }
}

/**
 * Main navigation display for the Trail app using Koin's Navigation 3 integration.
 * 
 * Navigation 3 with Koin integration benefits:
 * - Uses koinEntryProvider() to retrieve navigation entries from Koin modules
 * - Navigation entries are declared centrally in navigationModule
 * - Automatic dependency injection for screens
 * - Type-safe route handling via sealed interface
 * - Direct back stack management via mutableStateListOf
 * - Supports passing complex objects directly (via Kotlin Serialization)
 *
 * @param modifier Modifier for the NavDisplay
 * @param sharedUrl Optional URL shared from external sources
 * @param authViewModel ViewModel for authentication state
 * @param navigationViewModel ViewModel for managing navigation back stack
 */
@OptIn(KoinExperimentalAPI::class)
@Composable
fun TrailNavHost(
    modifier: Modifier = Modifier,
    sharedUrl: String? = null,
    authViewModel: AuthViewModel = koinViewModel(),
    navigationViewModel: NavigationViewModel = koinViewModel()
) {
    val isLoggedIn by authViewModel.isLoggedIn.collectAsState(initial = false)
    
    // Retrieve navigation entries from Koin
    val entryProvider = koinEntryProvider<Any>()
    
    // Initialize back stack based on login state and shared URL
    val initialScreen = remember(isLoggedIn, sharedUrl) {
        when {
            !isLoggedIn -> Screen.Auth
            sharedUrl != null -> Screen.Share(sharedUrl)
            else -> Screen.EntryList
        }
    }
    
    // Set initial screen if back stack is empty
    if (navigationViewModel.backStack.isEmpty()) {
        navigationViewModel.backStack.add(initialScreen)
    }
    
    NavDisplay(
        backStack = navigationViewModel.backStack,
        modifier = modifier,
        transitionSpec = {
            // Smooth fade transition between screens
            fadeIn(tween(300)) togetherWith fadeOut(tween(300))
        },
        entryDecorators = listOf(
            // Default decorators for scene management and state saving
            rememberSaveableStateHolderNavEntryDecorator(),
            rememberSaveableStateHolderNavEntryDecorator(),
        ),
        entryProvider = entryProvider
    )
}
