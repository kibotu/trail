package net.kibotu.trail.feature.myfeed

import android.content.Context

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
import kotlinx.coroutines.launch
import android.net.Uri
import net.kibotu.trail.shared.comment.CommentRepository
import net.kibotu.trail.shared.comment.CommentStateManager
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
import net.kibotu.trail.shared.util.shareEntry

class MyFeedViewModel(
    private val entryRepository: EntryRepository,
    commentRepository: CommentRepository,
    private val userRepository: UserRepository,
    private val imageUploadManager: ImageUploadManager,
    private val nickname: String,
    private val inAppReviewManager: InAppReviewManager
) : ViewModel() {

    private val commentStateManager = CommentStateManager(commentRepository, viewModelScope)

    private val _pagingSource = MutableStateFlow<UserEntriesPagingSource?>(null)

    val entries: Flow<PagingData<Entry>> = Pager(
        config = PagingConfig(pageSize = 20, enablePlaceholders = false),
        pagingSourceFactory = {
            UserEntriesPagingSource(entryRepository, nickname).also {
                _pagingSource.value = it
            }
        }
    ).flow.cachedIn(viewModelScope)

    val commentsState: StateFlow<Map<Int, CommentState>> = commentStateManager.commentsState

    val currentlyPlayingVideoId: StateFlow<String?>
        field = MutableStateFlow<String?>(null)

    val isPosting: StateFlow<Boolean>
        field = MutableStateFlow(false)

    val reviewEvent: SharedFlow<Unit>
        field = MutableSharedFlow<Unit>(extraBufferCapacity = 1)

    fun onVideoPlay(id: String?) { currentlyPlayingVideoId.value = id }

    val uploadProgress: StateFlow<Float>
        field = MutableStateFlow(0f)

    fun createEntry(context: Context, text: String, imageUris: List<Uri> = emptyList()) {
        viewModelScope.launch {
            isPosting.value = true
            uploadProgress.value = 0f

            val imageIds = mutableListOf<Int>()
            for ((index, uri) in imageUris.withIndex()) {
                imageUploadManager.uploadImage(context, uri) { progress ->
                    val base = index.toFloat() / imageUris.size
                    val portion = 1f / imageUris.size
                    uploadProgress.value = base + progress * portion
                }.fold(
                    onSuccess = { imageId -> imageIds.add(imageId) },
                    onFailure = {
                        isPosting.value = false
                        uploadProgress.value = 0f
                        return@launch
                    }
                )
            }

            uploadProgress.value = 0f
            entryRepository.createEntry(CreateEntryRequest(text, imageIds.ifEmpty { null })).fold(
                onSuccess = {
                    _pagingSource.value?.invalidate()
                    if (inAppReviewManager.shouldPrompt()) {
                        reviewEvent.tryEmit(Unit)
                    }
                },
                onFailure = {}
            )
            isPosting.value = false
        }
    }

    fun toggleComments(entryId: Int, hashId: String?) = commentStateManager.toggleComments(entryId, hashId)
    fun loadComments(entryId: Int, hashId: String?) = commentStateManager.loadComments(entryId, hashId)
    fun createComment(entryId: Int, hashId: String?, text: String) = commentStateManager.createComment(entryId, hashId, text)
    fun updateComment(commentId: Int, text: String, entryId: Int) = commentStateManager.updateComment(commentId, text, entryId)
    fun deleteComment(commentId: Int, entryId: Int) = commentStateManager.deleteComment(commentId, entryId)
    fun clapComment(commentId: Int, count: Int, entryId: Int) = commentStateManager.clapComment(commentId, count)
    fun reportComment(commentId: Int, entryId: Int) = commentStateManager.reportComment(commentId)

    fun updateEntry(entryId: Int, text: String) { viewModelScope.launch { entryRepository.updateEntry(entryId, UpdateEntryRequest(text)) } }
    fun deleteEntry(entryId: Int) {
        viewModelScope.launch {
            entryRepository.deleteEntry(entryId).onSuccess {
                _pagingSource.value?.invalidate()
            }
        }
    }
    fun addClaps(hashId: String, count: Int) { viewModelScope.launch { entryRepository.addClaps(hashId, count) } }
    fun recordView(hashId: String) { viewModelScope.launch { entryRepository.recordView(hashId) } }
    fun reportEntry(hashId: String) { viewModelScope.launch { entryRepository.reportEntry(hashId) } }
    fun muteUser(userId: Int) { viewModelScope.launch { userRepository.muteUser(userId) } }

    fun shareEntry(context: Context, entry: Entry) {
        shareEntry(context, entry)
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
