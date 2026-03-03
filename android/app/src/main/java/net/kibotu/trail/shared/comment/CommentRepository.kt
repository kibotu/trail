package net.kibotu.trail.shared.comment

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

class CommentRepository(private val client: HttpClient) {

    suspend fun getComments(
        entryHashId: String,
        limit: Int = 50,
        before: String? = null
    ): Result<CommentsResponse> = runCatching {
        client.get("api/entries/$entryHashId/comments") {
            parameter("limit", limit)
            before?.let { parameter("before", it) }
        }.body()
    }

    suspend fun createComment(
        entryHashId: String,
        request: CreateCommentRequest
    ): Result<CreateCommentResponse> = runCatching {
        client.post("api/entries/$entryHashId/comments") {
            contentType(ContentType.Application.Json)
            setBody(request)
        }.body()
    }

    suspend fun updateComment(
        commentId: Int,
        request: UpdateCommentRequest
    ): Result<UpdateCommentResponse> = runCatching {
        client.put("api/comments/$commentId") {
            contentType(ContentType.Application.Json)
            setBody(request)
        }.body()
    }

    suspend fun deleteComment(commentId: Int): Result<Unit> = runCatching {
        client.delete("api/comments/$commentId")
    }

    suspend fun addClap(commentId: Int, count: Int): Result<CommentClapResponse> = runCatching {
        client.post("api/comments/$commentId/claps") {
            contentType(ContentType.Application.Json)
            setBody(CommentClapRequest(count))
        }.body()
    }

    suspend fun reportComment(commentId: Int): Result<Unit> = runCatching {
        client.post("api/comments/$commentId/report")
    }

    suspend fun recordView(commentId: Int): Result<Unit> = runCatching {
        client.post("api/comments/$commentId/views") {
            contentType(ContentType.Application.Json)
            setBody(mapOf("fingerprint" to null))
        }
    }
}
