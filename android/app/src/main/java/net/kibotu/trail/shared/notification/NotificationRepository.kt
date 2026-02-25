package net.kibotu.trail.shared.notification

import io.ktor.client.HttpClient
import io.ktor.client.call.body
import io.ktor.client.request.delete
import io.ktor.client.request.get
import io.ktor.client.request.parameter
import io.ktor.client.request.put
import io.ktor.client.request.setBody
import io.ktor.http.ContentType
import io.ktor.http.contentType

class NotificationRepository(private val client: HttpClient) {

    suspend fun getNotifications(
        limit: Int = 20,
        before: String? = null
    ): Result<NotificationsResponse> = runCatching {
        client.get("api/notifications") {
            parameter("limit", limit)
            before?.let { parameter("before", it) }
        }.body()
    }

    suspend fun markRead(notificationId: Int): Result<Unit> = runCatching {
        client.put("api/notifications/$notificationId/read")
    }

    suspend fun markAllRead(): Result<Unit> = runCatching {
        client.put("api/notifications/read-all")
    }

    suspend fun deleteNotification(notificationId: Int): Result<Unit> = runCatching {
        client.delete("api/notifications/$notificationId")
    }

    suspend fun getPreferences(): Result<NotificationPreferences> = runCatching {
        client.get("api/notifications/preferences").body()
    }

    suspend fun updatePreferences(prefs: NotificationPreferences): Result<Unit> = runCatching {
        client.put("api/notifications/preferences") {
            contentType(ContentType.Application.Json)
            setBody(prefs)
        }
    }
}
