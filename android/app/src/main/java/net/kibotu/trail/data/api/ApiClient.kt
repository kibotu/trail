package net.kibotu.trail.data.api

import io.ktor.client.*
import io.ktor.client.engine.android.*
import io.ktor.client.plugins.*
import io.ktor.client.plugins.contentnegotiation.*
import io.ktor.client.plugins.logging.*
import io.ktor.serialization.kotlinx.json.*
import kotlinx.serialization.json.Json
import net.kibotu.trail.BuildConfig

object ApiClient {
    // Base URL from local.properties (configured to match backend/secrets.yml)
    private val BASE_URL = BuildConfig.API_BASE_URL

    private var authToken: String? = null

    fun setAuthToken(token: String?) {
        authToken = token
    }

    val client = HttpClient(Android) {
        install(ContentNegotiation) {
            json(Json {
                ignoreUnknownKeys = true
                isLenient = true
                encodeDefaults = true
                prettyPrint = true
            })
        }

        install(Logging) {
            logger = Logger.ANDROID
            level = LogLevel.ALL
        }

        install(HttpTimeout) {
            requestTimeoutMillis = 30_000
            connectTimeoutMillis = 30_000
            socketTimeoutMillis = 30_000
        }

        defaultRequest {
            url(BASE_URL)
            authToken?.let {
                headers.append("Authorization", "Bearer $it")
            }
        }
    }

    val api = TrailApi(client)
}
