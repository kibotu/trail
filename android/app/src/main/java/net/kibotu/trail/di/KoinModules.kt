package net.kibotu.trail.di

import com.github.florent37.application.provider.application
import io.ktor.client.HttpClient
import io.ktor.client.engine.android.Android
import io.ktor.client.plugins.auth.Auth
import io.ktor.client.plugins.auth.providers.BearerTokens
import io.ktor.client.plugins.auth.providers.bearer
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.plugins.defaultRequest
import io.ktor.client.plugins.logging.LogLevel
import io.ktor.client.plugins.logging.Logger
import io.ktor.client.plugins.logging.Logging
import io.ktor.client.plugins.logging.SIMPLE
import io.ktor.client.request.header
import io.ktor.http.URLProtocol
import io.ktor.http.path
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.json.Json
import net.kibotu.resourceextension.resBoolean
import net.kibotu.trail.R
import net.kibotu.trail.data.api.TrailApiService
import net.kibotu.trail.data.repository.TrailRepository
import net.kibotu.trail.ui.auth.AuthScreen
import net.kibotu.trail.ui.auth.AuthViewModel
import net.kibotu.trail.ui.list.EntryListScreen
import net.kibotu.trail.ui.list.EntryListViewModel
import net.kibotu.trail.ui.navigation.NavigationViewModel
import net.kibotu.trail.ui.navigation.Screen
import net.kibotu.trail.ui.share.ShareScreen
import net.kibotu.trail.ui.share.ShareViewModel
import org.koin.android.ext.koin.androidContext
import org.koin.androidx.compose.koinViewModel
import org.koin.core.annotation.KoinExperimentalAPI
import org.koin.core.module.dsl.viewModel
import org.koin.core.module.dsl.viewModelOf
import org.koin.dsl.module
import org.koin.dsl.navigation3.navigation

val appModule = module {
    // Add any app-level dependencies here
}

val networkModule = module {
    single {
        HttpClient(Android) {
            install(ContentNegotiation) {
                json(Json {
                    ignoreUnknownKeys = true
                    isLenient = true
                    prettyPrint = true
                })
            }

            install(Logging) {
                logger = Logger.SIMPLE
                level = if (R.bool.development.resBoolean) LogLevel.INFO else LogLevel.NONE
            }

            install(Auth) {
                bearer {
                    loadTokens {
                        val repository = get<TrailRepository>()
                        val token = repository.getJwtToken()
                        token?.let { BearerTokens(it, "") }
                    }
                }
            }

            defaultRequest {
                url {
                    protocol = URLProtocol.HTTPS
                    host = "example.com" // TODO: Replace with actual host
                    path("trail/")
                }
                val version = application?.let { app ->
                    app.packageManager.getPackageInfo(app.packageName, 0)?.versionName
                } ?: -1
                header("User-Agent", "Trail Android/${version}")
            }
        }
    }

    single { TrailApiService(get()) }
}

val repositoryModule = module {
    single { TrailRepository(androidContext(), get()) }
}

val viewModelModule = module {
    viewModel { AuthViewModel(get()) }
    viewModel { AuthViewModel(get()) }
    viewModel { ShareViewModel(get()) }
    viewModel { EntryListViewModel(get()) }
    viewModel { NavigationViewModel() }
}

/**
 * Navigation module using Koin's Navigation 3 integration.
 * 
 * This module declares navigation entries using the navigation<T>() DSL function,
 * which automatically registers them with Koin's EntryProvider system.
 * 
 * Benefits over manual entryProvider:
 * - Centralized navigation configuration in Koin modules
 * - Automatic dependency injection for screens
 * - Type-safe route handling
 * - Easier testing and maintenance
 * 
 * Key differences from manual entryProvider approach:
 * - Navigation entries are declared in Koin modules instead of inline
 * - Use koinEntryProvider() in composables to retrieve entries
 * - Follows Koin's dependency injection patterns
 * - Better separation of concerns and testability
 */
@OptIn(KoinExperimentalAPI::class)
val navigationModule = module {
    // Auth Screen - Entry point for unauthenticated users
    navigation<Screen.Auth> { route ->
        val authViewModel: AuthViewModel = koinViewModel()
        val navigationViewModel: NavigationViewModel = koinViewModel()

        AuthScreen(
            onAuthSuccess = {
                // Navigate to appropriate destination after auth
                // Note: sharedUrl handling is done in TrailNavHost
                navigationViewModel.navigateAndClearBackStack(Screen.EntryList)
            },
            viewModel = authViewModel
        )
    }

    // Entry List Screen - Main screen showing trail entries
    navigation<Screen.EntryList> { route ->
        val authViewModel: AuthViewModel = koinViewModel()
        val navigationViewModel: NavigationViewModel = koinViewModel()

        EntryListScreen(
            viewModel = koinViewModel(),
            onSignOut = {
                authViewModel.logout()
                navigationViewModel.navigateAndClearBackStack(Screen.Auth)
            }
        )
    }

    // Share Screen - Handles shared URLs with type-safe parameter passing
    navigation<Screen.Share> { route ->
        val navigationViewModel: NavigationViewModel = koinViewModel()

        ShareScreen(
            sharedUrl = route.sharedUrl,
            onSuccess = {
                navigationViewModel.replaceWith(Screen.EntryList)
            },
            onCancel = {
                navigationViewModel.replaceWith(Screen.EntryList)
            },
            viewModel = koinViewModel()
        )
    }
}
