package net.kibotu.trail.feature.profile

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.profile.ProfileRepository
import net.kibotu.trail.shared.profile.ProfileResponse
import net.kibotu.trail.shared.profile.UpdateProfileRequest
import net.kibotu.trail.shared.user.FiltersResponse
import net.kibotu.trail.shared.user.UserRepository

data class ProfileScreenState(
    val profile: ProfileResponse? = null,
    val filters: FiltersResponse? = null,
    val isLoading: Boolean = true,
    val error: String? = null,
    val isUpdating: Boolean = false
)

class ProfileViewModel(
    private val profileRepository: ProfileRepository,
    private val userRepository: UserRepository
) : ViewModel() {

    private val _state = MutableStateFlow(ProfileScreenState())
    val state: StateFlow<ProfileScreenState> = _state.asStateFlow()

    init {
        loadProfile()
        loadFilters()
    }

    fun loadProfile() {
        viewModelScope.launch {
            _state.value = _state.value.copy(isLoading = true)
            profileRepository.getProfile().fold(
                onSuccess = { _state.value = _state.value.copy(profile = it, isLoading = false) },
                onFailure = { _state.value = _state.value.copy(error = it.message, isLoading = false) }
            )
        }
    }

    fun loadFilters() {
        viewModelScope.launch {
            userRepository.getFilters().onSuccess {
                _state.value = _state.value.copy(filters = it)
            }
        }
    }

    fun updateProfile(nickname: String, bio: String?) {
        viewModelScope.launch {
            _state.value = _state.value.copy(isUpdating = true)
            profileRepository.updateProfile(UpdateProfileRequest(nickname, bio)).fold(
                onSuccess = { loadProfile() },
                onFailure = { _state.value = _state.value.copy(isUpdating = false) }
            )
        }
    }

    fun unmuteUser(userId: Int) {
        viewModelScope.launch {
            userRepository.unmuteUser(userId).onSuccess { loadFilters() }
        }
    }

    class Factory(private val context: Context) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            val client = ApiClient.client
            return ProfileViewModel(ProfileRepository(client), UserRepository(client)) as T
        }
    }
}
