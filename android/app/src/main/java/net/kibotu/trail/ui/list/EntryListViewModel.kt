package net.kibotu.trail.ui.list

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.firebase.crashlytics.crashlytics
import com.google.firebase.Firebase
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.data.model.Entry
import net.kibotu.trail.data.repository.TrailRepository

sealed class EntryListState {
    object Loading : EntryListState()
    data class Success(val entries: List<Entry>, val hasMore: Boolean) : EntryListState()
    data class Error(val message: String) : EntryListState()
}

class EntryListViewModel(private val repository: TrailRepository) : ViewModel() {
    
    private val _listState = MutableStateFlow<EntryListState>(EntryListState.Loading)
    val listState: StateFlow<EntryListState> = _listState.asStateFlow()
    
    private var currentPage = 1
    private val pageSize = 20
    
    init {
        loadEntries()
    }
    
    fun loadEntries() {
        viewModelScope.launch {
            _listState.value = EntryListState.Loading
            
            val result = repository.getEntries(currentPage, pageSize)
            
            _listState.value = if (result.isSuccess) {
                val response = result.getOrNull()!!
                EntryListState.Success(
                    entries = response.entries,
                    hasMore = currentPage < response.pages
                )
            } else {
                val error = result.exceptionOrNull()
                Firebase.crashlytics.recordException(error ?: Exception("Unknown list error"))
                EntryListState.Error(error?.message ?: "Failed to load entries")
            }
        }
    }
    
    fun loadMore() {
        if (_listState.value is EntryListState.Success) {
            val currentState = _listState.value as EntryListState.Success
            if (currentState.hasMore) {
                currentPage++
                loadEntries()
            }
        }
    }
    
    fun refresh() {
        currentPage = 1
        loadEntries()
    }
}
