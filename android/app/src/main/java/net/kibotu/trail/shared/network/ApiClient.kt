package net.kibotu.trail.shared.network

import io.ktor.client.HttpClient
import io.ktor.client.engine.android.Android
import io.ktor.client.plugins.HttpTimeout
import io.ktor.client.plugins.compression.ContentEncoding
import io.ktor.client.plugins.contentnegotiation.ContentNegotiation
import io.ktor.client.plugins.defaultRequest
import io.ktor.client.plugins.logging.ANDROID
import io.ktor.client.plugins.logging.LogLevel
import io.ktor.client.plugins.logging.Logger
import io.ktor.client.plugins.logging.Logging
import io.ktor.serialization.kotlinx.json.json
import kotlinx.serialization.json.Json
import net.kibotu.trail.BuildConfig

object ApiClient {

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

        install(ContentEncoding) {
            gzip()
            deflate()
        }

        install(Logging) {
            logger = Logger.ANDROID
            level = LogLevel.ALL
        }

        install(HttpTimeout) {
            requestTimeoutMillis = 30_000
            connectTimeoutMillis = 8_000
            socketTimeoutMillis = 30_000
        }

        defaultRequest {
            url(BuildConfig.API_BASE_URL)
            authToken?.let {
                headers.append("Authorization", "Bearer $it")
            }
        }
    }
}
