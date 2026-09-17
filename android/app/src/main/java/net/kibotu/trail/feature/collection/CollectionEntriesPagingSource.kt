package net.kibotu.trail.feature.collection

import androidx.paging.PagingSource
import androidx.paging.PagingState
import net.kibotu.trail.shared.entry.Entry
import net.kibotu.trail.shared.entry.EntryCache
import net.kibotu.trail.shared.entry.EntryRepository

class CollectionEntriesPagingSource(
    private val repository: EntryRepository,
    private val slug: String,
    private val query: String? = null
) : PagingSource<String, Entry>() {

    override fun getRefreshKey(state: PagingState<String, Entry>): String? = null

    override suspend fun load(params: LoadParams<String>): LoadResult<String, Entry> {
        return try {
            val cursor = params.key
            val result = repository.getCollectionEntries(
                slug = slug,
                limit = params.loadSize,
                before = cursor,
                query = query
            )
            result.fold(
                onSuccess = { response ->
                    EntryCache.putAll(response.entries)
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
