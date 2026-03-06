package net.kibotu.trail.feature.auth

import android.content.Context
import androidx.compose.runtime.compositionLocalOf
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import net.kibotu.trail.shared.auth.AuthRepository
import net.kibotu.trail.shared.auth.AuthUser
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.profile.ProfileRepository
import net.kibotu.trail.shared.storage.TokenManager

data class AuthState(
    val isLoggedIn: Boolean = false,
    val isLoading: Boolean = true,
    val user: AuthUser? = null,
    val error: String? = null,
    val pendingSharedText: String? = null,
    val pendingDeletion: Boolean = false,
    val deletionRequestedAt: String? = null
)

class AuthViewModel(
    private val authRepository: AuthRepository,
    private val profileRepository: ProfileRepository
) : ViewModel() {

    val state: StateFlow<AuthState>
        field = MutableStateFlow(AuthState())

    init {
        viewModelScope.launch {
            val token = authRepository.getAuthToken()
            if (token != null) {
                ApiClient.setAuthToken(token)
                val nickname = authRepository.userNickname.first()
                val name = authRepository.userName.first()
                val userId = authRepository.userId.first()?.toIntOrNull()
                val photoUrl = authRepository.userPhotoUrl.first()

                state.value = AuthState(
                    isLoggedIn = true,
                    isLoading = false,
                    user = if (userId != null && (name != null || nickname != null)) {
                        AuthUser(
                            id = userId,
                            email = "",
                            name = name ?: nickname ?: "",
                            nickname = nickname,
                            isAdmin = false,
                            gravatarHash = "",
                            gravatarUrl = photoUrl ?: ""
                        )
                    } else null
                )

                profileRepository.getProfile().onSuccess { profile ->
                    val freshNickname = profile.nickname ?: nickname
                    freshNickname?.let { authRepository.saveNickname(it) }
                    state.value = state.value.copy(
                        pendingDeletion = profile.deletionRequestedAt != null,
                        deletionRequestedAt = profile.deletionRequestedAt,
                        user = state.value.user?.copy(
                            id = profile.id,
                            nickname = freshNickname,
                            isAdmin = profile.isAdmin ?: false
                        )
                    )
                }
            } else {
                state.value = AuthState(isLoggedIn = false, isLoading = false)
            }
        }
    }

    fun handleGoogleSignIn(idToken: String) {
        viewModelScope.launch {
            state.value = state.value.copy(isLoading = true, error = null)
            authRepository.googleSignIn(idToken).fold(
                onSuccess = { response ->
                    var user = response.user
                    var deletionRequestedAt: String? = null
                    profileRepository.getProfile().onSuccess { profile ->
                        val nickname = profile.nickname ?: user.nickname
                        nickname?.let { authRepository.saveNickname(it) }
                        user = user.copy(
                            nickname = nickname,
                            isAdmin = profile.isAdmin ?: user.isAdmin
                        )
                        deletionRequestedAt = profile.deletionRequestedAt
                    }
                    state.value = state.value.copy(
                        isLoggedIn = true,
                        isLoading = false,
                        user = user,
                        pendingDeletion = deletionRequestedAt != null,
                        deletionRequestedAt = deletionRequestedAt,
                        pendingSharedText = null
                    )
                },
                onFailure = { e ->
                    state.value = state.value.copy(
                        isLoading = false,
                        error = e.message
                    )
                }
            )
        }
    }

    fun logout() {
        viewModelScope.launch {
            authRepository.logout()
            state.value = AuthState(isLoggedIn = false, isLoading = false)
        }
    }

    fun setPendingSharedText(text: String) {
        state.value = state.value.copy(pendingSharedText = text)
    }

    fun consumePendingSharedText(): String? {
        val text = state.value.pendingSharedText
        state.value = state.value.copy(pendingSharedText = null)
        return text
    }

    fun clearPendingDeletion() {
        state.value = state.value.copy(pendingDeletion = false, deletionRequestedAt = null)
    }

    class Factory(private val context: Context) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            val tokenManager = TokenManager(context)
            val client = ApiClient.client
            val authRepository = AuthRepository(client, tokenManager)
            val profileRepository = ProfileRepository(client)
            return AuthViewModel(authRepository, profileRepository) as T
        }
    }
}

val LocalAuthViewModel = compositionLocalOf<AuthViewModel> {
    error("AuthViewModel not provided")
}
