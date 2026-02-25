package net.kibotu.trail.shared.notification

import androidx.compose.runtime.Stable
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Stable
@Serializable
data class Notification(
    val id: Int,
    val type: String,
    val message: String? = null,
    @SerialName("actor_name") val actorName: String? = null,
    @SerialName("actor_nickname") val actorNickname: String? = null,
    @SerialName("actor_avatar_url") val actorAvatarUrl: String? = null,
    @SerialName("entry_hash_id") val entryHashId: String? = null,
    @SerialName("entry_text") val entryText: String? = null,
    @SerialName("comment_id") val commentId: Int? = null,
    @SerialName("clap_count") val clapCount: Int? = null,
    @SerialName("is_read") val isRead: Boolean = false,
    @SerialName("created_at") val createdAt: String,
    @SerialName("grouped_actors") val groupedActors: List<GroupedActor>? = null
)

@Serializable
data class GroupedActor(
    val name: String,
    val nickname: String? = null,
    @SerialName("avatar_url") val avatarUrl: String? = null
)

@Serializable
data class NotificationsResponse(
    val notifications: List<Notification>,
    @SerialName("unread_count") val unreadCount: Int = 0,
    @SerialName("has_more") val hasMore: Boolean = false,
    @SerialName("next_cursor") val nextCursor: String? = null
)

@Serializable
data class NotificationPreferences(
    @SerialName("email_on_mention") val emailOnMention: Boolean = true,
    @SerialName("email_on_comment") val emailOnComment: Boolean = false,
    @SerialName("email_on_clap") val emailOnClap: Boolean = false,
    @SerialName("email_digest_frequency") val emailDigestFrequency: String = "instant"
)
