package net.kibotu.trail.shared.entry

import androidx.paging.PagingSource
import androidx.paging.PagingState

class UserEntriesPagingSource(
    private val repository: EntryRepository,
    private val nickname: String,
    private val query: String? = null
) : PagingSource<String, Entry>() {

    override fun getRefreshKey(state: PagingState<String, Entry>): String? = null

    override suspend fun load(params: LoadParams<String>): LoadResult<String, Entry> {
        return try {
            val cursor = params.key
            val result = repository.getUserEntries(
                nickname = nickname,
                limit = params.loadSize,
                before = cursor,
                query = query
            )
            result.fold(
                onSuccess = { response ->
                    LoadResult.Page(
                        data = response.entries,
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
