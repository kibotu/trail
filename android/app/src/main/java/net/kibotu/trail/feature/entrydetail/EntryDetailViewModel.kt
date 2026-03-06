package net.kibotu.trail.feature.entrydetail

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.shared.comment.Comment
import net.kibotu.trail.shared.comment.CommentRepository
import net.kibotu.trail.shared.comment.CreateCommentRequest
import net.kibotu.trail.shared.comment.UpdateCommentRequest
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.EntryRepository
import net.kibotu.trail.shared.util.shareEntry as shareEntryUtil
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

    val state: StateFlow<EntryDetailState>
        field = MutableStateFlow(EntryDetailState())

    val entryDeleted: SharedFlow<Unit>
        field = MutableSharedFlow<Unit>(extraBufferCapacity = 1)

    val currentlyPlayingVideoId: StateFlow<String?>
        field = MutableStateFlow<String?>(null)

    init {
        loadEntry()
        loadComments()
        recordView()
    }

    private fun loadEntry() {
        viewModelScope.launch {
            entryRepository.getEntry(hashId).fold(
                onSuccess = { state.value = state.value.copy(entry = it, isLoading = false) },
                onFailure = { state.value = state.value.copy(error = it.message, isLoading = false) }
            )
        }
    }

    private val viewedCommentIds = mutableSetOf<Int>()

    fun loadComments() {
        viewModelScope.launch {
            state.value = state.value.copy(isCommentsLoading = true)
            commentRepository.getComments(hashId).fold(
                onSuccess = { response ->
                    state.value = state.value.copy(comments = response.comments, isCommentsLoading = false)
                    response.comments.forEach { comment ->
                        if (viewedCommentIds.add(comment.id)) {
                            launch { commentRepository.recordView(comment.id) }
                        }
                    }
                },
                onFailure = { state.value = state.value.copy(isCommentsLoading = false) }
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
                entryDeleted.tryEmit(Unit)
            }
        }
    }
    fun onVideoPlay(id: String?) { currentlyPlayingVideoId.value = id }

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
        val entry = state.value.entry ?: return
        shareEntryUtil(context, entry)
    }

    class Factory(private val hashId: String) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            val client = ApiClient.client
            return EntryDetailViewModel(hashId, EntryRepository(client), CommentRepository(client), UserRepository(client)) as T
        }
    }
}
