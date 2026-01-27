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
    data object Initial : ShareState()
    data object Loading : ShareState()
    data object Success : ShareState()
    data class Error(val message: String) : ShareState()
}

class ShareViewModel(private val repository: TrailRepository) : ViewModel() {
    
    private val _shareState = MutableStateFlow<ShareState>(ShareState.Initial)
    val shareState: StateFlow<ShareState> = _shareState.asStateFlow()
    
    private val _text = MutableStateFlow("")
    val text: StateFlow<String> = _text.asStateFlow()
    
    fun setText(newText: String) {
        if (newText.length <= 280) {
            _text.value = newText
        }
    }
    
    fun setSharedUrl(url: String) {
        // If a URL is shared, prepend it to the text if not already present
        if (url.isNotBlank() && !_text.value.contains(url)) {
            val newText = if (_text.value.isBlank()) url else "$url ${_text.value}"
            if (newText.length <= 280) {
                _text.value = newText
            }
        }
    }
    
    fun shareEntry() {
        viewModelScope.launch {
            if (_text.value.isBlank()) {
                _shareState.value = ShareState.Error("Text is required")
                return@launch
            }
            
            _shareState.value = ShareState.Loading
            
            val result = repository.createEntry(_text.value)
            
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
        _text.value = ""
    }
}
