package net.kibotu.trail.shared.notification

import androidx.paging.PagingSource
import androidx.paging.PagingState

class NotificationsPagingSource(
    private val repository: NotificationRepository
) : PagingSource<String, Notification>() {

    override fun getRefreshKey(state: PagingState<String, Notification>): String? = null

    override suspend fun load(params: LoadParams<String>): LoadResult<String, Notification> {
        return try {
            val cursor = params.key
            val result = repository.getNotifications(
                limit = params.loadSize,
                before = cursor
            )
            result.fold(
                onSuccess = { response ->
                    LoadResult.Page(
                        data = response.notifications,
                        prevKey = null,
                        nextKey = if (response.hasMore) response.nextCursor else null
                    )
                },
                onFailure = { LoadResult.Error(it) }
            )
        } catch (e: Exception) {
            LoadResult.Error(e)
        }
    }
}
