package net.kibotu.trail.feature.entrydetail

import android.content.Context
import android.content.Intent
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.shared.comment.Comment
import net.kibotu.trail.shared.comment.CommentRepository
import net.kibotu.trail.shared.comment.CreateCommentRequest
import net.kibotu.trail.shared.comment.UpdateCommentRequest
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.EntryRepository
import net.kibotu.trail.shared.entry.UpdateEntryRequest
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.user.UserRepository

data class EntryDetailState(
    val entry: Entry? = null,
    val comments: List<Comment> = emptyList(),
    val isLoading: Boolean = true,
    val isCommentsLoading: Boolean = false,
    val error: String? = null
)

class EntryDetailViewModel(
    private val hashId: String,
    private val entryRepository: EntryRepository,
    private val commentRepository: CommentRepository,
    private val userRepository: UserRepository
) : ViewModel() {

    private val _state = MutableStateFlow(EntryDetailState())
    val state: StateFlow<EntryDetailState> = _state.asStateFlow()

    private val _entryDeleted = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val entryDeleted: SharedFlow<Unit> = _entryDeleted.asSharedFlow()

    private val _currentlyPlayingVideoId = MutableStateFlow<String?>(null)
    val currentlyPlayingVideoId: StateFlow<String?> = _currentlyPlayingVideoId.asStateFlow()

    init {
        loadEntry()
        loadComments()
        recordView()
    }

    private fun loadEntry() {
        viewModelScope.launch {
            entryRepository.getEntry(hashId).fold(
                onSuccess = { _state.value = _state.value.copy(entry = it, isLoading = false) },
                onFailure = { _state.value = _state.value.copy(error = it.message, isLoading = false) }
            )
        }
    }

    private val viewedCommentIds = mutableSetOf<Int>()

    fun loadComments() {
        viewModelScope.launch {
            _state.value = _state.value.copy(isCommentsLoading = true)
            commentRepository.getComments(hashId).fold(
                onSuccess = { response ->
                    _state.value = _state.value.copy(comments = response.comments, isCommentsLoading = false)
                    response.comments.forEach { comment ->
                        if (viewedCommentIds.add(comment.id)) {
                            launch { commentRepository.recordView(comment.id) }
                        }
                    }
                },
                onFailure = { _state.value = _state.value.copy(isCommentsLoading = false) }
            )
        }
    }

    private fun recordView() {
        viewModelScope.launch { entryRepository.recordView(hashId) }
    }

    fun addClaps(count: Int) { viewModelScope.launch { entryRepository.addClaps(hashId, count) } }
    fun reportEntry() { viewModelScope.launch { entryRepository.reportEntry(hashId) } }
    fun muteUser(userId: Int) { viewModelScope.launch { userRepository.muteUser(userId) } }
    fun updateEntry(entryId: Int, text: String) { viewModelScope.launch { entryRepository.updateEntry(entryId, UpdateEntryRequest(text)) } }
    fun deleteEntry(entryId: Int) {
        viewModelScope.launch {
            entryRepository.deleteEntry(entryId).onSuccess {
                _entryDeleted.tryEmit(Unit)
            }
        }
    }
    fun onVideoPlay(id: String?) { _currentlyPlayingVideoId.value = id }

    fun createComment(text: String) {
        viewModelScope.launch {
            commentRepository.createComment(hashId, CreateCommentRequest(text)).onSuccess { loadComments() }
        }
    }

    fun updateComment(commentId: Int, text: String) {
        viewModelScope.launch {
            commentRepository.updateComment(commentId, UpdateCommentRequest(text)).onSuccess { loadComments() }
        }
    }

    fun deleteComment(commentId: Int) {
        viewModelScope.launch {
            commentRepository.deleteComment(commentId).onSuccess { loadComments() }
        }
    }

    fun clapComment(commentId: Int, count: Int) {
        viewModelScope.launch { commentRepository.addClap(commentId, count) }
    }

    fun reportComment(commentId: Int) {
        viewModelScope.launch { commentRepository.reportComment(commentId) }
    }

    fun shareEntry(context: Context) {
        val entry = _state.value.entry ?: return
        val url = "https://trail.kibotu.net/status/${entry.hashId}"
        val intent = Intent(Intent.ACTION_SEND).apply { type = "text/plain"; putExtra(Intent.EXTRA_TEXT, "${entry.text}\n$url") }
        context.startActivity(Intent.createChooser(intent, "Share entry"))
    }

    class Factory(private val hashId: String) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            val client = ApiClient.client
            return EntryDetailViewModel(hashId, EntryRepository(client), CommentRepository(client), UserRepository(client)) as T
        }
    }
}
