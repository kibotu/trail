package net.kibotu.trail.shared.user

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class MuteResponse(
    val success: Boolean? = null,
    val message: String? = null
)

@Serializable
data class MuteStatusResponse(
    @SerialName("is_muted") val isMuted: Boolean
)

@Serializable
data class FiltersResponse(
    @SerialName("muted_users") val mutedUsers: List<MutedUser> = emptyList()
)

@Serializable
data class MutedUser(
    val id: Int,
    val name: String,
    val nickname: String? = null,
    @SerialName("avatar_url") val avatarUrl: String? = null,
    @SerialName("muted_at") val mutedAt: String? = null
)
