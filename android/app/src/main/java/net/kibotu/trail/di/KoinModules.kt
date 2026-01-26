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
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import net.kibotu.resourceextension.resBoolean
import net.kibotu.trail.R
import net.kibotu.trail.data.api.TrailApiService
import net.kibotu.trail.data.repository.TrailRepository
import net.kibotu.trail.ui.auth.AuthViewModel
import net.kibotu.trail.ui.list.EntryListViewModel
import net.kibotu.trail.ui.share.ShareViewModel
import org.koin.android.ext.koin.androidContext
import org.koin.androidx.viewmodel.dsl.viewModel
import org.koin.dsl.module

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
                        val token = runBlocking { repository.getJwtToken() }
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
    viewModel { ShareViewModel(get()) }
    viewModel { EntryListViewModel(get()) }
}
