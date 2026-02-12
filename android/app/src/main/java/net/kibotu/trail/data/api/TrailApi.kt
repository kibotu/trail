package net.kibotu.trail.data.api

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.delete
import io.ktor.client.request.get
import io.ktor.client.request.parameter
import io.ktor.client.request.post
import io.ktor.client.request.put
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType
import net.kibotu.trail.data.model.AuthResponse
import net.kibotu.trail.data.model.CommentClapRequest
import net.kibotu.trail.data.model.CommentClapResponse
import net.kibotu.trail.data.model.CommentsResponse
import net.kibotu.trail.data.model.CreateCommentRequest
import net.kibotu.trail.data.model.CreateCommentResponse
import net.kibotu.trail.data.model.CreateEntryRequest
import net.kibotu.trail.data.model.CreateEntryResponse
import net.kibotu.trail.data.model.EntriesResponse
import net.kibotu.trail.data.model.GoogleAuthRequest
import net.kibotu.trail.data.model.ProfileResponse
import net.kibotu.trail.data.model.UpdateCommentRequest
import net.kibotu.trail.data.model.UpdateCommentResponse
import net.kibotu.trail.data.model.UpdateEntryRequest
import net.kibotu.trail.data.model.UpdateEntryResponse
import net.kibotu.trail.data.model.UpdateProfileRequest
import net.kibotu.trail.data.model.UpdateProfileResponse

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

    suspend fun getEntries(
        limit: Int = 20,
        before: String? = null,
        query: String? = null
    ): Result<EntriesResponse> {
        return try {
            val response = client.get("api/entries") {
                parameter("limit", limit)
                before?.let { parameter("before", it) }
                query?.let { parameter("q", it) }
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

    suspend fun updateEntry(
        entryId: Int,
        request: UpdateEntryRequest
    ): Result<UpdateEntryResponse> {
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

    // Comment endpoints
    suspend fun getComments(
        entryId: Int,
        limit: Int = 50,
        before: String? = null
    ): Result<CommentsResponse> {
        return try {
            val response = client.get("api/entries/$entryId/comments") {
                parameter("limit", limit)
                before?.let { parameter("before", it) }
            }
            Result.success(response.body<CommentsResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun createComment(
        entryId: Int,
        request: CreateCommentRequest
    ): Result<CreateCommentResponse> {
        return try {
            val response = client.post("api/entries/$entryId/comments") {
                contentType(ContentType.Application.Json)
                setBody(request)
            }
            Result.success(response.body<CreateCommentResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateComment(
        commentId: Int,
        request: UpdateCommentRequest
    ): Result<UpdateCommentResponse> {
        return try {
            val response = client.put("api/comments/$commentId") {
                contentType(ContentType.Application.Json)
                setBody(request)
            }
            Result.success(response.body<UpdateCommentResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteComment(commentId: Int): Result<Unit> {
        return try {
            client.delete("api/comments/$commentId")
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    // Comment clap endpoints
    suspend fun addCommentClap(commentId: Int, count: Int): Result<CommentClapResponse> {
        return try {
            val response = client.post("api/comments/$commentId/claps") {
                contentType(ContentType.Application.Json)
                setBody(CommentClapRequest(count))
            }
            Result.success(response.body<CommentClapResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getCommentClaps(commentId: Int): Result<CommentClapResponse> {
        return try {
            val response = client.get("api/comments/$commentId/claps")
            Result.success(response.body<CommentClapResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    // Comment report endpoint
    suspend fun reportComment(commentId: Int): Result<Unit> {
        return try {
            client.post("api/comments/$commentId/report")
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    // User entries endpoint
    suspend fun getUserEntries(
        nickname: String,
        limit: Int = 20,
        before: String? = null,
        query: String? = null
    ): Result<EntriesResponse> {
        return try {
            val response = client.get("api/users/$nickname/entries") {
                parameter("limit", limit)
                before?.let { parameter("before", it) }
                query?.let { parameter("q", it) }
            }
            Result.success(response.body<EntriesResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    // Profile endpoints
    suspend fun getProfile(): Result<ProfileResponse> {
        return try {
            val response = client.get("api/profile")
            Result.success(response.body<ProfileResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateProfile(request: UpdateProfileRequest): Result<UpdateProfileResponse> {
        return try {
            val response = client.put("api/profile") {
                contentType(ContentType.Application.Json)
                setBody(request)
            }
            Result.success(response.body<UpdateProfileResponse>())
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
