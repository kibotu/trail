package net.kibotu.trail.data.api

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.get
import io.ktor.client.request.post
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType
import net.kibotu.trail.data.model.*

class TrailApiService(private val client: HttpClient) {
    
    suspend fun authenticateWithGoogle(googleToken: String): Result<AuthResponse> {
        return try {
            val response = client.post("/api/auth/google") {
                contentType(ContentType.Application.Json)
                setBody(GoogleAuthRequest(googleToken))
            }
            Result.success(response.body())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun createEntry(text: String): Result<CreateEntryResponse> {
        return try {
            val response = client.post("/api/entries") {
                contentType(ContentType.Application.Json)
                setBody(CreateEntryRequest(text))
            }
            Result.success(response.body())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getEntries(page: Int = 1, limit: Int = 20): Result<EntriesResponse> {
        return try {
            val response = client.get("/api/entries") {
                url {
                    parameters.append("page", page.toString())
                    parameters.append("limit", limit.toString())
                }
            }
            Result.success(response.body())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
