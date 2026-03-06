package net.kibotu.trail.feature.home

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
import net.kibotu.trail.shared.comment.Comment
import net.kibotu.trail.shared.comment.CommentRepository
import net.kibotu.trail.shared.comment.CommentStateManager
import net.kibotu.trail.shared.entry.EntriesPagingSource
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.EntryRepository
import net.kibotu.trail.shared.entry.UpdateEntryRequest
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.user.UserRepository


data class CommentState(
    val comments: List<Comment> = emptyList(),
    val isLoading: Boolean = false,
    val isExpanded: Boolean = false
)

class HomeViewModel(
    private val entryRepository: EntryRepository,
    commentRepository: CommentRepository,
    private val userRepository: UserRepository
) : ViewModel() {

    private val commentStateManager = CommentStateManager(commentRepository, viewModelScope)

    private val _pagingSource = MutableStateFlow<EntriesPagingSource?>(null)

    val entries: Flow<PagingData<Entry>> = Pager(
        config = PagingConfig(pageSize = 20, enablePlaceholders = false),
        pagingSourceFactory = {
            EntriesPagingSource(entryRepository).also {
                _pagingSource.value = it
            }
        }
    ).flow.cachedIn(viewModelScope)

    val commentsState: StateFlow<Map<Int, CommentState>> = commentStateManager.commentsState

    val currentlyPlayingVideoId: StateFlow<String?>
        field = MutableStateFlow<String?>(null)

    fun onVideoPlay(id: String?) {
        currentlyPlayingVideoId.value = id
    }

    fun toggleComments(entryId: Int, hashId: String?) = commentStateManager.toggleComments(entryId, hashId)
    fun loadComments(entryId: Int, hashId: String?) = commentStateManager.loadComments(entryId, hashId)
    fun createComment(entryId: Int, hashId: String?, text: String) = commentStateManager.createComment(entryId, hashId, text)
    fun updateComment(commentId: Int, text: String, entryId: Int) = commentStateManager.updateComment(commentId, text, entryId)
    fun deleteComment(commentId: Int, entryId: Int) = commentStateManager.deleteComment(commentId, entryId)
    fun clapComment(commentId: Int, count: Int, entryId: Int) = commentStateManager.clapComment(commentId, count)
    fun reportComment(commentId: Int, entryId: Int) = commentStateManager.reportComment(commentId)

    fun updateEntry(entryId: Int, text: String) {
        viewModelScope.launch {
            entryRepository.updateEntry(entryId, UpdateEntryRequest(text))
        }
    }

    fun deleteEntry(entryId: Int) {
        viewModelScope.launch {
            entryRepository.deleteEntry(entryId).onSuccess {
                _pagingSource.value?.invalidate()
            }
        }
    }

    fun addClaps(hashId: String, count: Int) {
        viewModelScope.launch {
            entryRepository.addClaps(hashId, count)
        }
    }

    fun recordView(hashId: String) {
        viewModelScope.launch {
            entryRepository.recordView(hashId)
        }
    }

    fun reportEntry(hashId: String) {
        viewModelScope.launch {
            entryRepository.reportEntry(hashId)
        }
    }

    fun muteUser(userId: Int) {
        viewModelScope.launch {
            userRepository.muteUser(userId)
        }
    }

    class Factory : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            val client = ApiClient.client
            return HomeViewModel(
                EntryRepository(client),
                CommentRepository(client),
                UserRepository(client)
            ) as T
        }
    }
}
