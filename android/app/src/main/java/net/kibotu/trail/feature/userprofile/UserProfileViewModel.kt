package net.kibotu.trail.feature.userprofile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.paging.Pager
import androidx.paging.PagingConfig
import androidx.paging.PagingData
import androidx.paging.cachedIn
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.EntryRepository
import net.kibotu.trail.shared.entry.UserEntriesPagingSource
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.profile.ProfileResponse
import net.kibotu.trail.shared.user.UserRepository


data class UserProfileState(
    val profile: ProfileResponse? = null,
    val isLoading: Boolean = true,
    val error: String? = null,
    val isMuted: Boolean = false
)

class UserProfileViewModel(
    private val nickname: String,
    private val userRepository: UserRepository,
    private val entryRepository: EntryRepository
) : ViewModel() {

    val state: StateFlow<UserProfileState>
        field = MutableStateFlow(UserProfileState())

    val entries: Flow<PagingData<Entry>> = Pager(
        config = PagingConfig(pageSize = 20, enablePlaceholders = false),
        pagingSourceFactory = { UserEntriesPagingSource(entryRepository, nickname) }
    ).flow.cachedIn(viewModelScope)

    init {
        loadProfile()
        recordView()
    }

    private fun loadProfile() {
        viewModelScope.launch {
            userRepository.getPublicProfile(nickname).fold(
                onSuccess = { state.value = state.value.copy(profile = it, isLoading = false) },
                onFailure = { state.value = state.value.copy(error = it.message, isLoading = false) }
            )
        }
    }

    private fun recordView() {
        viewModelScope.launch { userRepository.recordProfileView(nickname) }
    }

    fun muteUser() {
        val userId = state.value.profile?.id ?: return
        viewModelScope.launch {
            userRepository.muteUser(userId).onSuccess {
                state.value = state.value.copy(isMuted = true)
            }
        }
    }

    fun unmuteUser() {
        val userId = state.value.profile?.id ?: return
        viewModelScope.launch {
            userRepository.unmuteUser(userId).onSuccess {
                state.value = state.value.copy(isMuted = false)
            }
        }
    }

    fun addClaps(hashId: String, count: Int) {
        viewModelScope.launch { entryRepository.addClaps(hashId, count) }
    }

    class Factory(private val nickname: String) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            val client = ApiClient.client
            return UserProfileViewModel(nickname, UserRepository(client), EntryRepository(client)) as T
        }
    }
}
