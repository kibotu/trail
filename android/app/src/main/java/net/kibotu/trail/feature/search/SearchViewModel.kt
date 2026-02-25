package net.kibotu.trail.feature.search

import android.content.Context
import android.content.Intent
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.paging.Pager
import androidx.paging.PagingConfig
import androidx.paging.PagingData
import androidx.paging.cachedIn
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.FlowPreview
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.debounce
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.launch
import net.kibotu.trail.shared.entry.EntriesPagingSource
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.EntryRepository
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.user.UserRepository

class SearchViewModel(
    private val entryRepository: EntryRepository,
    private val userRepository: UserRepository
) : ViewModel() {

    private val _query = MutableStateFlow("")
    val query: StateFlow<String> = _query.asStateFlow()

    @OptIn(FlowPreview::class, ExperimentalCoroutinesApi::class)
    val searchResults: Flow<PagingData<Entry>> = _query
        .debounce(300)
        .flatMapLatest { q ->
            Pager(
                config = PagingConfig(pageSize = 20, enablePlaceholders = false),
                pagingSourceFactory = { EntriesPagingSource(entryRepository, query = q.ifBlank { null }) }
            ).flow
        }
        .cachedIn(viewModelScope)

    fun updateQuery(newQuery: String) {
        _query.value = newQuery
    }

    fun addClaps(hashId: String, count: Int) {
        viewModelScope.launch { entryRepository.addClaps(hashId, count) }
    }

    fun muteUser(userId: Int) {
        viewModelScope.launch { userRepository.muteUser(userId) }
    }

    fun reportEntry(hashId: String) {
        viewModelScope.launch { entryRepository.reportEntry(hashId) }
    }

    fun shareEntry(context: Context, entry: Entry) {
        val url = "https://trail.kibotu.net/status/${entry.hashId}"
        val intent = Intent(Intent.ACTION_SEND).apply { type = "text/plain"; putExtra(Intent.EXTRA_TEXT, "${entry.text}\n$url") }
        context.startActivity(Intent.createChooser(intent, "Share entry"))
    }

    class Factory(private val context: Context) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            val client = ApiClient.client
            return SearchViewModel(EntryRepository(client), UserRepository(client)) as T
        }
    }
}
