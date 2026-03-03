package net.kibotu.trail.shared.comment

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.feature.home.CommentState

class CommentStateManager(
    private val commentRepository: CommentRepository,
    private val scope: CoroutineScope
) {
    val commentsState: StateFlow<Map<Int, CommentState>>
        field = MutableStateFlow<Map<Int, CommentState>>(emptyMap())

    private val viewedCommentIds = mutableSetOf<Int>()

    fun toggleComments(entryId: Int, hashId: String?) {
        val current = commentsState.value[entryId] ?: CommentState()
        val newExpanded = !current.isExpanded
        commentsState.value = commentsState.value + (entryId to current.copy(isExpanded = newExpanded))
        if (newExpanded && current.comments.isEmpty() && hashId != null) {
            loadComments(entryId, hashId)
        }
    }

    fun loadComments(entryId: Int, hashId: String?) {
        if (hashId == null) return
        val current = commentsState.value[entryId] ?: CommentState()
        commentsState.value = commentsState.value + (entryId to current.copy(isLoading = true))
        scope.launch {
            commentRepository.getComments(hashId).fold(
                onSuccess = { response ->
                    val updated = commentsState.value[entryId] ?: CommentState()
                    commentsState.value = commentsState.value + (entryId to updated.copy(
                        comments = response.comments,
                        isLoading = false
                    ))
                    response.comments.forEach { comment ->
                        if (viewedCommentIds.add(comment.id)) {
                            launch { commentRepository.recordView(comment.id) }
                        }
                    }
                },
                onFailure = {
                    val updated = commentsState.value[entryId] ?: CommentState()
                    commentsState.value = commentsState.value + (entryId to updated.copy(isLoading = false))
                }
            )
        }
    }

    fun createComment(entryId: Int, hashId: String?, text: String) {
        if (hashId == null) return
        scope.launch {
            commentRepository.createComment(hashId, CreateCommentRequest(text)).onSuccess {
                loadComments(entryId, hashId)
            }
        }
    }

    fun updateComment(commentId: Int, text: String, entryId: Int) {
        scope.launch {
            commentRepository.updateComment(commentId, UpdateCommentRequest(text)).onSuccess {
                val current = commentsState.value[entryId] ?: return@onSuccess
                commentsState.value = commentsState.value + (entryId to current.copy(
                    comments = current.comments.map { c ->
                        if (c.id == commentId) c.copy(text = text, updatedAt = it.updatedAt) else c
                    }
                ))
            }
        }
    }

    fun deleteComment(commentId: Int, entryId: Int) {
        scope.launch {
            commentRepository.deleteComment(commentId).onSuccess {
                val current = commentsState.value[entryId] ?: return@onSuccess
                commentsState.value = commentsState.value + (entryId to current.copy(
                    comments = current.comments.filter { it.id != commentId }
                ))
            }
        }
    }

    fun clapComment(commentId: Int, count: Int) {
        scope.launch { commentRepository.addClap(commentId, count) }
    }

    fun reportComment(commentId: Int) {
        scope.launch { commentRepository.reportComment(commentId) }
    }
}
