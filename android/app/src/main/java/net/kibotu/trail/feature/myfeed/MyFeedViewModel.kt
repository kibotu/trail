package net.kibotu.trail.feature.myfeed

import android.content.Context
import android.content.Intent
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import androidx.paging.Pager
import androidx.paging.PagingConfig
import androidx.paging.PagingData
import androidx.paging.cachedIn
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import android.net.Uri
import net.kibotu.trail.shared.comment.Comment
import net.kibotu.trail.shared.comment.CommentRepository
import net.kibotu.trail.shared.comment.CreateCommentRequest
import net.kibotu.trail.shared.entry.CreateEntryRequest
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.EntryRepository
import net.kibotu.trail.shared.entry.UpdateEntryRequest
import net.kibotu.trail.shared.entry.UserEntriesPagingSource
import net.kibotu.trail.shared.image.ImageUploadManager
import net.kibotu.trail.shared.image.ImageUploadRepository
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.review.InAppReviewManager
import net.kibotu.trail.shared.user.UserRepository
import net.kibotu.trail.feature.home.CommentState

class MyFeedViewModel(
    private val entryRepository: EntryRepository,
    private val commentRepository: CommentRepository,
    private val userRepository: UserRepository,
    private val imageUploadManager: ImageUploadManager,
    private val nickname: String,
    private val inAppReviewManager: InAppReviewManager
) : ViewModel() {

    private val _pagingSource = MutableStateFlow<UserEntriesPagingSource?>(null)

    val entries: Flow<PagingData<Entry>> = Pager(
        config = PagingConfig(pageSize = 20, enablePlaceholders = false),
        pagingSourceFactory = {
            UserEntriesPagingSource(entryRepository, nickname).also {
                _pagingSource.value = it
            }
        }
    ).flow.cachedIn(viewModelScope)

    private val _commentsState = MutableStateFlow<Map<Int, CommentState>>(emptyMap())
    val commentsState: StateFlow<Map<Int, CommentState>> = _commentsState.asStateFlow()

    private val _currentlyPlayingVideoId = MutableStateFlow<String?>(null)
    val currentlyPlayingVideoId: StateFlow<String?> = _currentlyPlayingVideoId.asStateFlow()

    private val _isPosting = MutableStateFlow(false)
    val isPosting: StateFlow<Boolean> = _isPosting.asStateFlow()

    private val _reviewEvent = MutableSharedFlow<Unit>(extraBufferCapacity = 1)
    val reviewEvent: SharedFlow<Unit> = _reviewEvent.asSharedFlow()

    fun onVideoPlay(id: String?) { _currentlyPlayingVideoId.value = id }

    private val _uploadProgress = MutableStateFlow(0f)
    val uploadProgress: StateFlow<Float> = _uploadProgress.asStateFlow()

    fun createEntry(text: String, imageIds: List<Int> = emptyList()) {
        viewModelScope.launch {
            _isPosting.value = true
            entryRepository.createEntry(CreateEntryRequest(text, imageIds.ifEmpty { null })).fold(
                onSuccess = {
                    _pagingSource.value?.invalidate()
                    if (inAppReviewManager.shouldPrompt()) {
                        _reviewEvent.tryEmit(Unit)
                    }
                },
                onFailure = {}
            )
            _isPosting.value = false
        }
    }

    fun uploadImage(context: Context, uri: Uri, onComplete: (Int) -> Unit) {
        viewModelScope.launch {
            imageUploadManager.uploadImage(context, uri) { progress ->
                _uploadProgress.value = progress
            }.fold(
                onSuccess = { imageId ->
                    _uploadProgress.value = 0f
                    onComplete(imageId)
                },
                onFailure = {
                    _uploadProgress.value = 0f
                }
            )
        }
    }

    fun toggleComments(entryId: Int, hashId: String?) {
        val current = _commentsState.value[entryId] ?: CommentState()
        val newExpanded = !current.isExpanded
        _commentsState.value = _commentsState.value + (entryId to current.copy(isExpanded = newExpanded))
        if (newExpanded && current.comments.isEmpty() && hashId != null) {
            loadComments(entryId, hashId)
        }
    }

    private val viewedCommentIds = mutableSetOf<Int>()

    fun loadComments(entryId: Int, hashId: String?) {
        if (hashId == null) return
        val current = _commentsState.value[entryId] ?: CommentState()
        _commentsState.value = _commentsState.value + (entryId to current.copy(isLoading = true))
        viewModelScope.launch {
            commentRepository.getComments(hashId).fold(
                onSuccess = { response ->
                    val updated = _commentsState.value[entryId] ?: CommentState()
                    _commentsState.value = _commentsState.value + (entryId to updated.copy(comments = response.comments, isLoading = false))
                    response.comments.forEach { comment ->
                        if (viewedCommentIds.add(comment.id)) {
                            launch { commentRepository.recordView(comment.id) }
                        }
                    }
                },
                onFailure = {
                    val updated = _commentsState.value[entryId] ?: CommentState()
                    _commentsState.value = _commentsState.value + (entryId to updated.copy(isLoading = false))
                }
            )
        }
    }

    fun createComment(entryId: Int, hashId: String?, text: String) {
        if (hashId == null) return
        viewModelScope.launch {
            commentRepository.createComment(hashId, CreateCommentRequest(text)).onSuccess { loadComments(entryId, hashId) }
        }
    }

    fun updateEntry(entryId: Int, text: String) { viewModelScope.launch { entryRepository.updateEntry(entryId, UpdateEntryRequest(text)) } }
    fun deleteEntry(entryId: Int) { viewModelScope.launch { entryRepository.deleteEntry(entryId) } }
    fun addClaps(hashId: String, count: Int) { viewModelScope.launch { entryRepository.addClaps(hashId, count) } }
    fun recordView(hashId: String) { viewModelScope.launch { entryRepository.recordView(hashId) } }
    fun reportEntry(hashId: String) { viewModelScope.launch { entryRepository.reportEntry(hashId) } }
    fun muteUser(userId: Int) { viewModelScope.launch { userRepository.muteUser(userId) } }

    fun updateComment(commentId: Int, text: String, entryId: Int) {
        viewModelScope.launch { commentRepository.updateComment(commentId, net.kibotu.trail.shared.comment.UpdateCommentRequest(text)) }
    }
    fun deleteComment(commentId: Int, entryId: Int) { viewModelScope.launch { commentRepository.deleteComment(commentId) } }
    fun clapComment(commentId: Int, count: Int, entryId: Int) { viewModelScope.launch { commentRepository.addClap(commentId, count) } }
    fun reportComment(commentId: Int, entryId: Int) { viewModelScope.launch { commentRepository.reportComment(commentId) } }

    fun shareEntry(context: Context, entry: Entry) {
        val url = "https://trail.kibotu.net/status/${entry.hashId}"
        val intent = Intent(Intent.ACTION_SEND).apply { type = "text/plain"; putExtra(Intent.EXTRA_TEXT, "${entry.text}\n$url") }
        context.startActivity(Intent.createChooser(intent, "Share entry"))
    }

    class Factory(
        private val context: Context,
        private val nickname: String,
        private val inAppReviewManager: InAppReviewManager
    ) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            val client = ApiClient.client
            val imageUploadManager = ImageUploadManager(ImageUploadRepository(client))
            return MyFeedViewModel(EntryRepository(client), CommentRepository(client), UserRepository(client), imageUploadManager, nickname, inAppReviewManager) as T
        }
    }
}
