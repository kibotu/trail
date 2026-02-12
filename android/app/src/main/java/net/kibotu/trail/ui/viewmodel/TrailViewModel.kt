package net.kibotu.trail.ui.viewmodel

import android.content.Context
import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import net.kibotu.trail.data.api.ApiClient
import net.kibotu.trail.data.model.Comment
import net.kibotu.trail.data.model.CreateCommentRequest
import net.kibotu.trail.data.model.CreateEntryRequest
import net.kibotu.trail.data.model.Entry
import net.kibotu.trail.data.model.GoogleAuthRequest
import net.kibotu.trail.data.model.ProfileResponse
import net.kibotu.trail.data.model.UpdateCommentRequest
import net.kibotu.trail.data.model.UpdateEntryRequest
import net.kibotu.trail.data.model.UpdateProfileRequest
import net.kibotu.trail.data.storage.TokenManager

sealed class UiState {
    object Loading : UiState()
    object Login : UiState()
    data class PublicEntries(
        val entries: List<Entry> = emptyList(),
        val isLoading: Boolean = false
    ) : UiState()

    data class Entries(
        val entries: List<Entry> = emptyList(),
        val userName: String = "",
        val userId: Int = 0,
        val isAdmin: Boolean = false,
        val isLoading: Boolean = false
    ) : UiState()

    data class Error(val message: String) : UiState()
}

// Comment state per entry
data class CommentState(
    val comments: List<Comment> = emptyList(),
    val isLoading: Boolean = false,
    val isExpanded: Boolean = false
)

// Search overlay state
enum class SearchType { HOME, MY_FEED }

data class SearchOverlayState(
    val isVisible: Boolean = false,
    val query: String = "",
    val results: List<Entry> = emptyList(),
    val isLoading: Boolean = false,
    val hasMore: Boolean = false,
    val nextCursor: String? = null,
    val searchType: SearchType = SearchType.HOME
)

class TrailViewModel(private val context: Context) : ViewModel() {
    private val tokenManager = TokenManager(context)
    private val _uiState = MutableStateFlow<UiState>(UiState.Loading)
    val uiState: StateFlow<UiState> = _uiState.asStateFlow()

    private val _celebrationEvent = MutableStateFlow(false)
    val celebrationEvent: StateFlow<Boolean> = _celebrationEvent.asStateFlow()

    private val _commentsState = MutableStateFlow<Map<Int, CommentState>>(emptyMap())
    val commentsState: StateFlow<Map<Int, CommentState>> = _commentsState.asStateFlow()

    // New StateFlows for tab-specific data
    private val _homeEntries = MutableStateFlow<List<Entry>>(emptyList())
    val homeEntries: StateFlow<List<Entry>> = _homeEntries.asStateFlow()

    private val _homeLoading = MutableStateFlow(false)
    val homeLoading: StateFlow<Boolean> = _homeLoading.asStateFlow()

    private val _myFeedEntries = MutableStateFlow<List<Entry>>(emptyList())
    val myFeedEntries: StateFlow<List<Entry>> = _myFeedEntries.asStateFlow()

    private val _myFeedLoading = MutableStateFlow(false)
    val myFeedLoading: StateFlow<Boolean> = _myFeedLoading.asStateFlow()

    private val _profileState = MutableStateFlow<ProfileResponse?>(null)
    val profileState: StateFlow<ProfileResponse?> = _profileState.asStateFlow()

    private val _profileLoading = MutableStateFlow(false)
    val profileLoading: StateFlow<Boolean> = _profileLoading.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    // Search overlay state
    private val _searchOverlayState = MutableStateFlow(SearchOverlayState())
    val searchOverlayState: StateFlow<SearchOverlayState> = _searchOverlayState.asStateFlow()

    // Video playback state - only one video plays at a time
    private val _currentlyPlayingVideoId = MutableStateFlow<String?>(null)
    val currentlyPlayingVideoId: StateFlow<String?> = _currentlyPlayingVideoId.asStateFlow()

    private var pendingSharedText: String? = null
    private var currentUserNickname: String? = null

    init {
        checkAuthStatus()
    }

    private fun checkAuthStatus() {
        viewModelScope.launch {
            try {
                val token = tokenManager.getAuthToken()
                if (token != null) {
                    // Get user info from token manager
                    val userName = tokenManager.userName.first() ?: ""
                    val userId = tokenManager.userId.first()?.toIntOrNull() ?: 0
                    val nickname = tokenManager.userNickname.first()

                    currentUserNickname = nickname
                    ApiClient.setAuthToken(token)
                    _uiState.value = UiState.Entries(
                        userName = userName,
                        userId = userId,
                        isAdmin = false // Will be updated from auth response if needed
                    )
                    loadHomeEntries()
                    // Load profile first to get nickname, then load feed
                    loadProfile()
                    // If we have nickname from storage, load feed immediately
                    if (nickname != null) {
                        loadMyFeedEntries(nickname)
                    }

                    // If there's pending shared text, submit it
                    pendingSharedText?.let { text ->
                        submitEntry(text)
                        pendingSharedText = null
                    }
                } else {
                    // Start with public entries view
                    _uiState.value = UiState.PublicEntries()
                    loadPublicEntries()
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error checking auth status", e)
                _uiState.value = UiState.PublicEntries()
                loadPublicEntries()
            }
        }
    }

    fun loadPublicEntries() {
        viewModelScope.launch {
            try {
                val currentState = _uiState.value
                if (currentState is UiState.PublicEntries) {
                    _uiState.value = currentState.copy(isLoading = true)
                }
                _homeLoading.value = true

                val result = ApiClient.api.getEntries()

                result.onSuccess { entriesResponse ->
                    // Update homeEntries for the Home tab
                    _homeEntries.value = entriesResponse.entries
                    _homeLoading.value = false
                    
                    if (currentState is UiState.PublicEntries) {
                        _uiState.value = currentState.copy(
                            entries = entriesResponse.entries,
                            isLoading = false
                        )
                    }
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to load public entries", e)
                    _homeLoading.value = false
                    if (currentState is UiState.PublicEntries) {
                        _uiState.value = currentState.copy(isLoading = false)
                    }
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error loading public entries", e)
                _homeLoading.value = false
                val currentState = _uiState.value
                if (currentState is UiState.PublicEntries) {
                    _uiState.value = currentState.copy(isLoading = false)
                }
            }
        }
    }

    fun navigateToLogin() {
        _uiState.value = UiState.Login
    }

    fun handleGoogleSignIn(idToken: String) {
        viewModelScope.launch {
            try {
                _uiState.value = UiState.Loading

                val result = ApiClient.api.googleAuth(GoogleAuthRequest(idToken))

                result.onSuccess { authResponse ->
                    // Save token
                    tokenManager.saveAuthToken(
                        token = authResponse.token,
                        email = authResponse.user.email,
                        name = authResponse.user.name,
                        userId = authResponse.user.id,
                        nickname = authResponse.user.nickname,
                        photoUrl = authResponse.user.gravatarUrl
                    )

                    currentUserNickname = authResponse.user.nickname

                    // Set token for API client
                    ApiClient.setAuthToken(authResponse.token)

                    // Navigate to entries
                    _uiState.value = UiState.Entries(
                        userName = authResponse.user.name,
                        userId = authResponse.user.id,
                        isAdmin = authResponse.user.isAdmin
                    )
                    loadHomeEntries()
                    // Load profile first to get the nickname
                    loadProfile()
                    // If auth response has nickname, load feed immediately
                    if (authResponse.user.nickname != null) {
                        loadMyFeedEntries(authResponse.user.nickname)
                    }

                    // Submit pending shared text if available
                    pendingSharedText?.let { text ->
                        submitEntry(text)
                        pendingSharedText = null
                    }
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Error during Google sign in", e)
                    _uiState.value = UiState.Error("Authentication failed: ${e.message}")
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error during Google sign in", e)
                _uiState.value = UiState.Error("Authentication failed: ${e.message}")
            }
        }
    }

    fun loadEntries() {
        viewModelScope.launch {
            try {
                val currentState = _uiState.value
                if (currentState is UiState.Entries) {
                    _uiState.value = currentState.copy(isLoading = true)
                }

                val result = ApiClient.api.getEntries()

                result.onSuccess { entriesResponse ->
                    if (currentState is UiState.Entries) {
                        _uiState.value = currentState.copy(
                            entries = entriesResponse.entries,
                            isLoading = false
                        )
                    } else {
                        _uiState.value = UiState.Entries(
                            entries = entriesResponse.entries,
                            isLoading = false
                        )
                    }
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to load entries", e)
                    if (currentState is UiState.Entries) {
                        _uiState.value = currentState.copy(isLoading = false)
                    }
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error loading entries", e)
                val currentState = _uiState.value
                if (currentState is UiState.Entries) {
                    _uiState.value = currentState.copy(isLoading = false)
                }
            }
        }
    }

    fun submitEntry(text: String) {
        viewModelScope.launch {
            try {
                val result = ApiClient.api.createEntry(CreateEntryRequest(text))

                result.onSuccess {
                    // CELEBRATE! 🎉
                    _celebrationEvent.value = true

                    // Reload both home and my feed after successful submission
                    loadHomeEntries()
                    refreshMyFeed()
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to submit entry", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error submitting entry", e)
            }
        }
    }

    fun resetCelebration() {
        _celebrationEvent.value = false
    }

    fun logout() {
        viewModelScope.launch {
            tokenManager.clearAuthToken()
            ApiClient.setAuthToken(null)
            currentUserNickname = null
            _homeEntries.value = emptyList()
            _myFeedEntries.value = emptyList()
            _profileState.value = null
            _searchQuery.value = ""
            _uiState.value = UiState.PublicEntries()
            loadPublicEntries()
        }
    }

    fun setPendingSharedText(text: String) {
        pendingSharedText = text
    }

    fun updateEntry(entryId: Int, text: String) {
        viewModelScope.launch {
            try {
                val result = ApiClient.api.updateEntry(entryId, UpdateEntryRequest(text))

                result.onSuccess {
                    // Reload both feeds after successful update
                    loadHomeEntries()
                    refreshMyFeed()
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to update entry", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error updating entry", e)
            }
        }
    }

    fun deleteEntry(entryId: Int) {
        viewModelScope.launch {
            try {
                val result = ApiClient.api.deleteEntry(entryId)

                result.onSuccess {
                    // Reload both feeds after successful deletion
                    loadHomeEntries()
                    refreshMyFeed()
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to delete entry", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error deleting entry", e)
            }
        }
    }

    fun canModifyEntry(entry: Entry): Boolean {
        val currentState = _uiState.value
        return if (currentState is UiState.Entries) {
            currentState.isAdmin || entry.userId == currentState.userId
        } else {
            false
        }
    }

    // Comment operations
    // Store mapping from entryId to hashId for comment operations
    private val entryHashIdMap = mutableMapOf<Int, String>()

    fun toggleComments(entryId: Int, entryHashId: String?) {
        // Store the hashId mapping
        if (entryHashId != null) {
            entryHashIdMap[entryId] = entryHashId
        }

        val currentState = _commentsState.value[entryId] ?: CommentState()
        val newExpanded = !currentState.isExpanded

        _commentsState.value = _commentsState.value.toMutableMap().apply {
            put(entryId, currentState.copy(isExpanded = newExpanded))
        }

        // Load comments on first expand
        if (newExpanded && currentState.comments.isEmpty()) {
            loadComments(entryId, entryHashId)
        }
    }

    fun loadComments(entryId: Int, entryHashId: String? = null) {
        // Try to get hashId from parameter or stored mapping
        val hashId = entryHashId ?: entryHashIdMap[entryId]
        if (hashId == null) {
            Log.e("TrailViewModel", "Cannot load comments: no hashId for entryId $entryId")
            return
        }

        viewModelScope.launch {
            try {
                val currentState = _commentsState.value[entryId] ?: CommentState()
                _commentsState.value = _commentsState.value.toMutableMap().apply {
                    put(entryId, currentState.copy(isLoading = true))
                }

                val result = ApiClient.api.getComments(hashId)

                result.onSuccess { response ->
                    _commentsState.value = _commentsState.value.toMutableMap().apply {
                        put(
                            entryId, CommentState(
                                comments = response.comments,
                                isLoading = false,
                                isExpanded = true
                            )
                        )
                    }
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to load comments", e)
                    _commentsState.value = _commentsState.value.toMutableMap().apply {
                        put(entryId, currentState.copy(isLoading = false))
                    }
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error loading comments", e)
            }
        }
    }

    fun createComment(entryId: Int, entryHashId: String?, text: String) {
        // Try to get hashId from parameter or stored mapping
        val hashId = entryHashId ?: entryHashIdMap[entryId]
        if (hashId == null) {
            Log.e("TrailViewModel", "Cannot create comment: no hashId for entryId $entryId")
            return
        }

        viewModelScope.launch {
            try {
                val result = ApiClient.api.createComment(hashId, CreateCommentRequest(text))

                result.onSuccess {
                    // Reload comments and entries (to update comment count)
                    loadComments(entryId, hashId)
                    loadEntries()
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to create comment", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error creating comment", e)
            }
        }
    }

    fun updateComment(commentId: Int, text: String, entryId: Int) {
        viewModelScope.launch {
            try {
                val result = ApiClient.api.updateComment(commentId, UpdateCommentRequest(text))

                result.onSuccess {
                    loadComments(entryId)
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to update comment", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error updating comment", e)
            }
        }
    }

    fun deleteComment(commentId: Int, entryId: Int) {
        viewModelScope.launch {
            try {
                val result = ApiClient.api.deleteComment(commentId)

                result.onSuccess {
                    // Reload comments and entries (to update comment count)
                    loadComments(entryId)
                    loadEntries()
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to delete comment", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error deleting comment", e)
            }
        }
    }

    fun clapComment(commentId: Int, count: Int, entryId: Int) {
        viewModelScope.launch {
            try {
                val result = ApiClient.api.addCommentClap(commentId, count)

                result.onSuccess {
                    loadComments(entryId)
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to clap comment", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error clapping comment", e)
            }
        }
    }

    fun reportComment(commentId: Int, entryId: Int) {
        viewModelScope.launch {
            try {
                val result = ApiClient.api.reportComment(commentId)

                result.onSuccess {
                    // Reload comments (reported comment will be hidden)
                    loadComments(entryId)
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to report comment", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error reporting comment", e)
            }
        }
    }

    // New methods for tab-specific data
    fun loadHomeEntries(query: String? = null) {
        viewModelScope.launch {
            try {
                _homeLoading.value = true
                val result = ApiClient.api.getEntries(limit = 100, query = query)

                result.onSuccess { entriesResponse ->
                    _homeEntries.value = entriesResponse.entries
                    _homeLoading.value = false
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to load home entries", e)
                    _homeLoading.value = false
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error loading home entries", e)
                _homeLoading.value = false
            }
        }
    }

    fun loadMyFeedEntries(nickname: String, query: String? = null) {
        viewModelScope.launch {
            try {
                Log.d("TrailViewModel", "loadMyFeedEntries called with nickname: $nickname")
                _myFeedLoading.value = true
                val result = ApiClient.api.getUserEntries(nickname, limit = 100, query = query)

                result.onSuccess { entriesResponse ->
                    Log.d(
                        "TrailViewModel",
                        "loadMyFeedEntries success: ${entriesResponse.entries.size} entries"
                    )
                    _myFeedEntries.value = entriesResponse.entries
                    _myFeedLoading.value = false
                }.onFailure { e ->
                    Log.e(
                        "TrailViewModel",
                        "Failed to load my feed entries for nickname: $nickname",
                        e
                    )
                    _myFeedLoading.value = false
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error loading my feed entries for nickname: $nickname", e)
                _myFeedLoading.value = false
            }
        }
    }

    fun searchEntries(query: String) {
        _searchQuery.value = query
        loadHomeEntries(query.ifBlank { null })
    }

    fun clearSearch() {
        _searchQuery.value = ""
        loadHomeEntries()
    }

    fun loadProfile() {
        viewModelScope.launch {
            try {
                Log.d("TrailViewModel", "loadProfile called")
                _profileLoading.value = true
                val result = ApiClient.api.getProfile()

                result.onSuccess { profile ->
                    Log.d(
                        "TrailViewModel",
                        "loadProfile success - nickname: ${profile.nickname}, name: ${profile.name}"
                    )
                    _profileState.value = profile
                    _profileLoading.value = false

                    // Update stored nickname and load feed if nickname is available
                    if (profile.nickname != null) {
                        if (profile.nickname != currentUserNickname) {
                            Log.d(
                                "TrailViewModel",
                                "Updating currentUserNickname from ${currentUserNickname} to ${profile.nickname}"
                            )
                            currentUserNickname = profile.nickname
                        }
                        // Always load feed when profile loads with a nickname
                        loadMyFeedEntries(profile.nickname)
                    } else {
                        Log.w("TrailViewModel", "Profile loaded but nickname is null")
                    }
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to load profile", e)
                    _profileLoading.value = false
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error loading profile", e)
                _profileLoading.value = false
            }
        }
    }

    fun updateProfile(nickname: String?, bio: String?) {
        viewModelScope.launch {
            try {
                // Nickname is required by the API
                if (nickname.isNullOrBlank()) {
                    Log.e("TrailViewModel", "Nickname is required for profile update")
                    return@launch
                }
                val request = UpdateProfileRequest(nickname = nickname, bio = bio)
                val result = ApiClient.api.updateProfile(request)

                result.onSuccess {
                    // Reload profile to get updated data
                    loadProfile()
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Failed to update profile", e)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error updating profile", e)
            }
        }
    }

    fun refreshMyFeed() {
        // Try currentUserNickname first, fall back to profile nickname
        val nickname = currentUserNickname ?: _profileState.value?.nickname
        Log.d(
            "TrailViewModel",
            "refreshMyFeed called - currentUserNickname: $currentUserNickname, profileNickname: ${_profileState.value?.nickname}, using: $nickname"
        )
        if (nickname != null) {
            loadMyFeedEntries(nickname)
        } else {
            // If no nickname available, try to load profile first
            Log.w(
                "TrailViewModel",
                "refreshMyFeed called but no nickname available, loading profile first"
            )
            loadProfile()
        }
    }

    // Search overlay functions
    fun openSearch(type: SearchType) {
        _searchOverlayState.value = SearchOverlayState(
            isVisible = true,
            searchType = type
        )
    }

    fun closeSearch() {
        _searchOverlayState.value = SearchOverlayState()
    }

    fun updateSearchQuery(query: String) {
        _searchOverlayState.value = _searchOverlayState.value.copy(query = query)
    }

    fun executeSearch(query: String) {
        if (query.isBlank()) return

        viewModelScope.launch {
            try {
                _searchOverlayState.value = _searchOverlayState.value.copy(
                    isLoading = true,
                    results = emptyList(),
                    nextCursor = null,
                    hasMore = false
                )

                val currentState = _searchOverlayState.value
                val result = when (currentState.searchType) {
                    SearchType.HOME -> ApiClient.api.getEntries(
                        limit = PAGE_SIZE,
                        query = query
                    )
                    SearchType.MY_FEED -> {
                        val nickname = currentUserNickname ?: _profileState.value?.nickname
                        if (nickname != null) {
                            ApiClient.api.getUserEntries(
                                nickname = nickname,
                                limit = PAGE_SIZE,
                                query = query
                            )
                        } else {
                            Log.w("TrailViewModel", "Cannot search My Feed: no nickname available")
                            _searchOverlayState.value = _searchOverlayState.value.copy(isLoading = false)
                            return@launch
                        }
                    }
                }

                result.onSuccess { entriesResponse ->
                    _searchOverlayState.value = _searchOverlayState.value.copy(
                        results = entriesResponse.entries,
                        isLoading = false,
                        hasMore = entriesResponse.hasMore,
                        nextCursor = entriesResponse.nextCursor
                    )
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Search failed", e)
                    _searchOverlayState.value = _searchOverlayState.value.copy(isLoading = false)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error during search", e)
                _searchOverlayState.value = _searchOverlayState.value.copy(isLoading = false)
            }
        }
    }

    fun loadMoreSearchResults() {
        val currentState = _searchOverlayState.value
        if (currentState.isLoading || !currentState.hasMore || currentState.nextCursor == null) {
            return
        }

        viewModelScope.launch {
            try {
                _searchOverlayState.value = currentState.copy(isLoading = true)

                val result = when (currentState.searchType) {
                    SearchType.HOME -> ApiClient.api.getEntries(
                        limit = PAGE_SIZE,
                        before = currentState.nextCursor,
                        query = currentState.query.ifBlank { null }
                    )
                    SearchType.MY_FEED -> {
                        val nickname = currentUserNickname ?: _profileState.value?.nickname
                        if (nickname != null) {
                            ApiClient.api.getUserEntries(
                                nickname = nickname,
                                limit = PAGE_SIZE,
                                before = currentState.nextCursor,
                                query = currentState.query.ifBlank { null }
                            )
                        } else {
                            Log.w("TrailViewModel", "Cannot load more My Feed results: no nickname available")
                            _searchOverlayState.value = _searchOverlayState.value.copy(isLoading = false)
                            return@launch
                        }
                    }
                }

                result.onSuccess { entriesResponse ->
                    _searchOverlayState.value = _searchOverlayState.value.copy(
                        results = currentState.results + entriesResponse.entries,
                        isLoading = false,
                        hasMore = entriesResponse.hasMore,
                        nextCursor = entriesResponse.nextCursor
                    )
                }.onFailure { e ->
                    Log.e("TrailViewModel", "Load more search results failed", e)
                    _searchOverlayState.value = _searchOverlayState.value.copy(isLoading = false)
                }
            } catch (e: Exception) {
                Log.e("TrailViewModel", "Error loading more search results", e)
                _searchOverlayState.value = _searchOverlayState.value.copy(isLoading = false)
            }
        }
    }

    // Video playback functions
    
    /**
     * Start playing a video. Stops any other currently playing video.
     * Pass null to stop all video playback.
     */
    fun playVideo(videoId: String?) {
        _currentlyPlayingVideoId.value = videoId
    }

    /**
     * Stop all video playback
     */
    fun stopAllVideos() {
        _currentlyPlayingVideoId.value = null
    }

    companion object {
        private const val PAGE_SIZE = 20
    }
}
