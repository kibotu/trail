package net.kibotu.trail.ui.share

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.firebase.crashlytics.crashlytics
import com.google.firebase.Firebase
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.data.repository.TrailRepository

sealed class ShareState {
    object Initial : ShareState()
    object Loading : ShareState()
    object Success : ShareState()
    data class Error(val message: String) : ShareState()
}

class ShareViewModel(private val repository: TrailRepository) : ViewModel() {
    
    private val _shareState = MutableStateFlow<ShareState>(ShareState.Initial)
    val shareState: StateFlow<ShareState> = _shareState.asStateFlow()
    
    private val _url = MutableStateFlow("")
    val url: StateFlow<String> = _url.asStateFlow()
    
    private val _message = MutableStateFlow("")
    val message: StateFlow<String> = _message.asStateFlow()
    
    fun setUrl(newUrl: String) {
        _url.value = newUrl
    }
    
    fun setMessage(newMessage: String) {
        if (newMessage.length <= 280) {
            _message.value = newMessage
        }
    }
    
    fun shareEntry() {
        viewModelScope.launch {
            if (_url.value.isBlank() || _message.value.isBlank()) {
                _shareState.value = ShareState.Error("URL and message are required")
                return@launch
            }
            
            _shareState.value = ShareState.Loading
            
            val result = repository.createEntry(_url.value, _message.value)
            
            _shareState.value = if (result.isSuccess) {
                ShareState.Success
            } else {
                val error = result.exceptionOrNull()
                Firebase.crashlytics.recordException(error ?: Exception("Unknown share error"))
                ShareState.Error(error?.message ?: "Failed to share entry")
            }
        }
    }
    
    fun resetState() {
        _shareState.value = ShareState.Initial
        _url.value = ""
        _message.value = ""
    }
}
