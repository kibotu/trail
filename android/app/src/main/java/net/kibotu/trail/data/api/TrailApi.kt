package net.kibotu.trail.data.api

import io.ktor.client.*
import io.ktor.client.call.*
import io.ktor.client.request.*
import io.ktor.http.*
import net.kibotu.trail.data.model.*

class TrailApi(private val client: HttpClient) {
    
    suspend fun googleAuth(request: GoogleAuthRequest): Result<AuthResponse> {
        return try {
            val response = client.post("api/auth/google") {
                contentType(ContentType.Application.Json)
                setBody(request)
            }
            Result.success(response.body<AuthResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getEntries(page: Int = 1, limit: Int = 20): Result<EntriesResponse> {
        return try {
            val response = client.get("api/entries") {
                parameter("page", page)
                parameter("limit", limit)
            }
            Result.success(response.body<EntriesResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun createEntry(request: CreateEntryRequest): Result<CreateEntryResponse> {
        return try {
            val response = client.post("api/entries") {
                contentType(ContentType.Application.Json)
                setBody(request)
            }
            Result.success(response.body<CreateEntryResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateEntry(entryId: Int, request: UpdateEntryRequest): Result<UpdateEntryResponse> {
        return try {
            val response = client.put("api/entries/$entryId") {
                contentType(ContentType.Application.Json)
                setBody(request)
            }
            Result.success(response.body<UpdateEntryResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteEntry(entryId: Int): Result<Unit> {
        return try {
            client.delete("api/entries/$entryId")
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
