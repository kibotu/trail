package net.kibotu.trail.feature.profile

import android.content.Context
import android.content.Intent
import androidx.core.content.FileProvider
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.launch
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.profile.ProfileRepository
import net.kibotu.trail.shared.profile.ProfileResponse
import net.kibotu.trail.shared.profile.UpdateProfileRequest
import net.kibotu.trail.shared.user.FiltersResponse
import net.kibotu.trail.shared.user.UserRepository
import java.io.File

data class ProfileScreenState(
    val profile: ProfileResponse? = null,
    val filters: FiltersResponse? = null,
    val isLoading: Boolean = true,
    val error: String? = null,
    val isUpdating: Boolean = false,
    val isExporting: Boolean = false
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

    fun downloadExport(context: Context) {
        viewModelScope.launch {
            _state.value = _state.value.copy(isExporting = true)
            profileRepository.exportData().fold(
                onSuccess = { bytes ->
                    try {
                        val file = File(context.cacheDir, "trail-data-export.html")
                        file.writeBytes(bytes)
                        val uri = FileProvider.getUriForFile(context, "${context.packageName}.provider", file)
                        val intent = Intent(Intent.ACTION_VIEW).apply {
                            setDataAndType(uri, "text/html")
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                        }
                        context.startActivity(intent)
                    } catch (e: Exception) {
                        _state.value = _state.value.copy(error = "Could not open export file")
                    }
                    _state.value = _state.value.copy(isExporting = false)
                },
                onFailure = {
                    _state.value = _state.value.copy(isExporting = false, error = it.message)
                }
            )
        }
    }

    fun requestDeletion(onSuccess: () -> Unit) {
        viewModelScope.launch {
            profileRepository.requestDeletion().fold(
                onSuccess = { onSuccess() },
                onFailure = { _state.value = _state.value.copy(error = it.message) }
            )
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
