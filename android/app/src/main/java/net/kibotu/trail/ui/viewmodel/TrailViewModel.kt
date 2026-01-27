package net.kibotu.trail.ui.viewmodel

import android.content.Context
import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import net.kibotu.trail.data.api.ApiClient
import net.kibotu.trail.data.model.*
import net.kibotu.trail.data.storage.TokenManager

sealed class UiState {
    object Loading : UiState()
    object Login : UiState()
    data class Entries(
        val entries: List<Entry> = emptyList(),
        val userName: String = "",
        val isLoading: Boolean = false
    ) : UiState()
    data class Error(val message: String) : UiState()
}

class TrailViewModel(private val context: Context) : ViewModel() {
    private val tokenManager = TokenManager(context)
    private val _uiState = MutableStateFlow<UiState>(UiState.Loading)
    val uiState: StateFlow<UiState> = _uiState.asStateFlow()

    private var pendingSharedText: String? = null

    init {
        checkAuthStatus()
    }

    private fun checkAuthStatus() {
        viewModelScope.launch {
            try {
                val token = tokenManager.getAuthToken()
                if (token != null) {
                    // Get user name from token manager
                    val userName = tokenManager.userName.first() ?: ""
                    
                    ApiClient.setAuthToken(token)
                    _uiState.value = UiState.Entries(userName = userName)
                    loadEntries()
                    
                    // If there's pending shared text, submit it
                    pendingSharedText?.let { text ->
                        submitEntry(text)
                        pendingSharedText = null
                    }
                } else {
                    _uiState.value = UiState.Login
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error checking auth status", e)
                _uiState.value = UiState.Login
            }
        }
    }

    fun handleGoogleSignIn(idToken: String) {
        viewModelScope.launch {
            try {
                _uiState.value = UiState.Loading
                
                val result = ApiClient.api.googleAuth(GoogleAuthRequest(idToken))
                
                result.onSuccess { authResponse ->
                    // Save token
                    tokenManager.saveAuthToken(
                        token = authResponse.token,
                        email = authResponse.user.email,
                        name = authResponse.user.name,
                        userId = authResponse.user.id
                    )
                    
                    // Set token for API client
                    ApiClient.setAuthToken(authResponse.token)
                    
                    // Navigate to entries
                    _uiState.value = UiState.Entries(userName = authResponse.user.name)
                    loadEntries()
                    
                    // Submit pending shared text if available
                    pendingSharedText?.let { text ->
                        submitEntry(text)
                        pendingSharedText = null
                    }
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Error during Google sign in", e)
                    _uiState.value = UiState.Error("Authentication failed: ${e.message}")
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error during Google sign in", e)
                _uiState.value = UiState.Error("Authentication failed: ${e.message}")
            }
        }
    }

    fun loadEntries() {
        viewModelScope.launch {
            try {
                val currentState = _uiState.value
                if (currentState is UiState.Entries) {
                    _uiState.value = currentState.copy(isLoading = true)
                }
                
                val result = ApiClient.api.getEntries()
                
                result.onSuccess { entriesResponse ->
                    val currentUserName = if (currentState is UiState.Entries) {
                        currentState.userName
                    } else {
                        ""
                    }
                    
                    _uiState.value = UiState.Entries(
                        entries = entriesResponse.entries,
                        userName = currentUserName,
                        isLoading = false
                    )
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to load entries", e)
                    if (currentState is UiState.Entries) {
                        _uiState.value = currentState.copy(isLoading = false)
                    }
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error loading entries", e)
                val currentState = _uiState.value
                if (currentState is UiState.Entries) {
                    _uiState.value = currentState.copy(isLoading = false)
                }
            }
        }
    }

    fun submitEntry(text: String) {
        viewModelScope.launch {
            try {
                val result = ApiClient.api.createEntry(CreateEntryRequest(text))
                
                result.onSuccess {
                    // Reload entries after successful submission
                    loadEntries()
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to submit entry", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error submitting entry", e)
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            tokenManager.clearAuthToken()
            ApiClient.setAuthToken(null)
            _uiState.value = UiState.Login
        }
    }

    fun setPendingSharedText(text: String) {
        pendingSharedText = text
    }
}
