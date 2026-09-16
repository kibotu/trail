package net.kibotu.trail.feature.collection

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
import io.ktor.client.request.post
import net.kibotu.trail.shared.entry.CollectionDetail
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.EntryRepository
import net.kibotu.trail.shared.network.ApiClient


data class CollectionState(
    val collection: CollectionDetail? = null,
    val isLoading: Boolean = true,
    val error: String? = null
)

class CollectionViewModel(
    private val slug: String,
    private val entryRepository: EntryRepository
) : ViewModel() {

    val state: StateFlow<CollectionState>
        field = MutableStateFlow(CollectionState())

    val entries: Flow<PagingData<Entry>> = Pager(
        config = PagingConfig(pageSize = 20, enablePlaceholders = false),
        pagingSourceFactory = { CollectionEntriesPagingSource(entryRepository, slug) }
    ).flow.cachedIn(viewModelScope)

    init {
        loadCollection()
        recordView()
    }

    private fun loadCollection() {
        viewModelScope.launch {
            entryRepository.getCollection(slug).fold(
                onSuccess = { state.value = state.value.copy(collection = it.collection, isLoading = false) },
                onFailure = { state.value = state.value.copy(error = it.message, isLoading = false) }
            )
        }
    }

    private fun recordView() {
        viewModelScope.launch {
            runCatching {
                ApiClient.client.post("api/collections/$slug/views")
            }
        }
    }

    fun addClaps(hashId: String, count: Int) {
        viewModelScope.launch { entryRepository.addClaps(hashId, count) }
    }

    class Factory(private val slug: String) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            return CollectionViewModel(slug, EntryRepository(ApiClient.client)) as T
        }
    }
}
