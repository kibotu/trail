package net.kibotu.trail.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.firebase.crashlytics.crashlytics
import com.google.firebase.Firebase
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.data.model.AuthResponse
import net.kibotu.trail.data.repository.TrailRepository

sealed class AuthState {
    object Initial : AuthState()
    object Loading : AuthState()
    data class Success(val authResponse: AuthResponse) : AuthState()
    data class Error(val message: String) : AuthState()
}

class AuthViewModel(private val repository: TrailRepository) : ViewModel() {
    
    private val _authState = MutableStateFlow<AuthState>(AuthState.Initial)
    val authState: StateFlow<AuthState> = _authState.asStateFlow()
    
    val isLoggedIn = repository.isLoggedIn
    
    fun authenticateWithGoogle(googleToken: String) {
        viewModelScope.launch {
            _authState.value = AuthState.Loading
            
            val result = repository.authenticateWithGoogle(googleToken)
            
            _authState.value = if (result.isSuccess) {
                val authResponse = result.getOrNull()!!
                Firebase.crashlytics.setUserId(authResponse.user.id.toString())
                AuthState.Success(authResponse)
            } else {
                val error = result.exceptionOrNull()
                Firebase.crashlytics.recordException(error ?: Exception("Unknown auth error"))
                AuthState.Error(error?.message ?: "Authentication failed")
            }
        }
    }
    
    fun logout() {
        viewModelScope.launch {
            repository.logout()
            Firebase.crashlytics.setUserId("")
            _authState.value = AuthState.Initial
        }
    }
}
