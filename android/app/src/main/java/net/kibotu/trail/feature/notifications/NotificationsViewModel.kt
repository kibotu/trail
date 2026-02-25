package net.kibotu.trail.feature.notifications

import android.content.Context
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
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.notification.Notification
import net.kibotu.trail.shared.notification.NotificationRepository
import net.kibotu.trail.shared.notification.NotificationsPagingSource

class NotificationsViewModel(
    private val repository: NotificationRepository
) : ViewModel() {

    val notifications: Flow<PagingData<Notification>> = Pager(
        config = PagingConfig(pageSize = 20, enablePlaceholders = false),
        pagingSourceFactory = { NotificationsPagingSource(repository) }
    ).flow.cachedIn(viewModelScope)

    private val _unreadCount = MutableStateFlow(0)
    val unreadCount: StateFlow<Int> = _unreadCount.asStateFlow()

    init {
        refreshUnreadCount()
    }

    private fun refreshUnreadCount() {
        viewModelScope.launch {
            repository.getNotifications(limit = 1).onSuccess {
                _unreadCount.value = it.unreadCount
            }
        }
    }

    fun markRead(notificationId: Int) {
        viewModelScope.launch {
            repository.markRead(notificationId).onSuccess { refreshUnreadCount() }
        }
    }

    fun markAllRead() {
        viewModelScope.launch {
            repository.markAllRead().onSuccess { refreshUnreadCount() }
        }
    }

    fun deleteNotification(notificationId: Int) {
        viewModelScope.launch {
            repository.deleteNotification(notificationId)
        }
    }

    class Factory(private val context: Context) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T {
            return NotificationsViewModel(NotificationRepository(ApiClient.client)) as T
        }
    }
}
