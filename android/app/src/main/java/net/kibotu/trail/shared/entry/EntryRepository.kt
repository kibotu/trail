package net.kibotu.trail.shared.entry

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

class EntryRepository(private val client: HttpClient) {

    suspend fun getEntries(
        limit: Int = 20,
        before: String? = null,
        query: String? = null
    ): Result<EntriesResponse> = runCatching {
        client.get("api/entries") {
            parameter("limit", limit)
            before?.let { parameter("before", it) }
            query?.let { parameter("q", it) }
        }.body()
    }

    suspend fun getEntry(hashId: String): Result<Entry> = runCatching {
        client.get("api/entries/$hashId").body()
    }

    suspend fun getUserEntries(
        nickname: String,
        limit: Int = 20,
        before: String? = null,
        query: String? = null
    ): Result<EntriesResponse> = runCatching {
        client.get("api/users/$nickname/entries") {
            parameter("limit", limit)
            before?.let { parameter("before", it) }
            query?.let { parameter("q", it) }
        }.body()
    }

    suspend fun createEntry(request: CreateEntryRequest): Result<CreateEntryResponse> = runCatching {
        client.post("api/entries") {
            contentType(ContentType.Application.Json)
            setBody(request)
        }.body()
    }

    suspend fun updateEntry(entryId: Int, request: UpdateEntryRequest): Result<UpdateEntryResponse> = runCatching {
        client.put("api/entries/$entryId") {
            contentType(ContentType.Application.Json)
            setBody(request)
        }.body()
    }

    suspend fun deleteEntry(entryId: Int): Result<Unit> = runCatching {
        client.delete("api/entries/$entryId")
    }

    suspend fun addClaps(hashId: String, count: Int): Result<ClapResponse> = runCatching {
        client.post("api/entries/$hashId/claps") {
            contentType(ContentType.Application.Json)
            setBody(ClapRequest(count))
        }.body()
    }

    suspend fun recordView(hashId: String, fingerprint: String? = null): Result<ViewResponse> = runCatching {
        client.post("api/entries/$hashId/views") {
            contentType(ContentType.Application.Json)
            setBody(ViewRequest(fingerprint))
        }.body()
    }

    suspend fun reportEntry(hashId: String, reason: String? = null): Result<ReportResponse> = runCatching {
        client.post("api/entries/$hashId/report") {
            contentType(ContentType.Application.Json)
            setBody(ReportRequest(reason))
        }.body()
    }
}
