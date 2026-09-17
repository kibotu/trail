package net.kibotu.trail.shared.navigation

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.AnimatedVisibilityScope
import androidx.compose.animation.ExperimentalSharedTransitionApi
import androidx.compose.animation.SharedTransitionLayout
import androidx.compose.animation.SharedTransitionScope
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NamedNavArgument
import androidx.navigation.NavBackStackEntry
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import dev.chrisbanes.haze.hazeEffect
import dev.chrisbanes.haze.hazeSource
import dev.chrisbanes.haze.rememberHazeState
import kotlinx.coroutines.flow.StateFlow
import net.kibotu.trail.feature.auth.DeletionBlockerScreen
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.feature.collection.CollectionScreen
import net.kibotu.trail.feature.entrydetail.EntryDetailScreen
import net.kibotu.trail.feature.home.HomeScreen
import net.kibotu.trail.feature.myfeed.MyFeedScreen
import net.kibotu.trail.feature.notifications.NotificationsScreen
import net.kibotu.trail.feature.profile.ProfileScreen
import net.kibotu.trail.feature.search.SearchScreen
import net.kibotu.trail.feature.share.ShareScreen
import net.kibotu.trail.feature.userprofile.UserProfileScreen
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.notification.NotificationRepository
import net.kibotu.trail.shared.storage.ThemePreferences
import net.kibotu.trail.shared.theme.LocalWindowSizeClass
import net.kibotu.trail.shared.theme.isCompactWidth
import net.kibotu.trail.shared.theme.ui.FloatingTabBar
import net.kibotu.trail.shared.theme.ui.FloatingTabBarDefaults
import net.kibotu.trail.shared.theme.ui.rememberFloatingTabBarScrollConnection
import java.net.URLEncoder

object Routes {
    const val HOME = "home"
    const val MY_FEED = "my_feed"
    const val PROFILE = "profile"
    const val SEARCH = "search?query={query}"
    const val ENTRY_DETAIL = "entry/{hashId}"
    const val USER_PROFILE = "user/{nickname}"
    const val COLLECTION = "collection/{slug}"
    const val NOTIFICATIONS = "notifications"
    const val SHARE = "share"

    val TABS = setOf(HOME, MY_FEED, PROFILE, SEARCH, NOTIFICATIONS)

    fun entryDetail(hashId: String) = "entry/$hashId"
    fun userProfile(nickname: String) = "user/$nickname"
    fun collection(slug: String) = "collection/$slug"
    fun search(query: String = "") =
        if (query.isNotBlank()) "search?query=${URLEncoder.encode(query, "UTF-8")}" else "search"
}

internal val LocalSharedTransitionScope = staticCompositionLocalOf<SharedTransitionScope?> { null }
internal val LocalAnimatedVisibilityScope =
    staticCompositionLocalOf<AnimatedVisibilityScope?> { null }

/**
 * Hash id of the one entry allowed to run a shared-bounds transition right now, or null.
 *
 * Feeds overlap by design: the same entry shows up in Home, My Feed and Search. Keying shared
 * content on the hash id alone would therefore make a tab switch match two list cards and fling
 * one across the screen. Gating on the entry being opened keeps at most one matched pair alive.
 */
internal val LocalSharedEntryId = compositionLocalOf<String?> { null }

/**
 * Registers a destination that can take part in shared element transitions.
 *
 * Every screen gets the scopes, including ones with no entry cards today, so that adding a card
 * later is not silently a no-op.
 */
@OptIn(ExperimentalSharedTransitionApi::class)
private fun NavGraphBuilder.screen(
    route: String,
    sharedTransitionScope: SharedTransitionScope,
    arguments: List<NamedNavArgument> = emptyList(),
    content: @Composable (NavBackStackEntry) -> Unit,
) = composable(route, arguments) { backStackEntry ->
    CompositionLocalProvider(
        LocalSharedTransitionScope provides sharedTransitionScope,
        LocalAnimatedVisibilityScope provides this@composable,
    ) {
        content(backStackEntry)
    }
}

@Composable
fun TrailNavigation(
    themePreferences: ThemePreferences,
    pendingSharedText: StateFlow<String?>,
    onConsumeSharedText: () -> Unit,
    modifier: Modifier = Modifier
) {
    val authViewModel = LocalAuthViewModel.current
    val authState by authViewModel.state.collectAsState()

    if (authState.pendingDeletion) {
        DeletionBlockerScreen(
            deletionRequestedAt = authState.deletionRequestedAt,
            onRevertSuccess = { authViewModel.clearPendingDeletion() },
            onLogout = { authViewModel.logout() }
        )
        return
    }

    var sharedTextForScreen by rememberSaveable { mutableStateOf<String?>(null) }
    val pendingText by pendingSharedText.collectAsState()

    LaunchedEffect(pendingText, authState.isLoading) {
        if (authState.isLoading) return@LaunchedEffect
        pendingText?.let { text ->
            sharedTextForScreen = text
            onConsumeSharedText()
        }
    }

    val sessionKey = authState.user?.id ?: 0

    key(sessionKey) {
        TrailNavigationContent(
            themePreferences = themePreferences,
            sharedTextForScreen = sharedTextForScreen,
            onSharedTextConsumed = { sharedTextForScreen = null },
            modifier = modifier
        )
    }
}

@Composable
private fun TrailNavigationContent(
    themePreferences: ThemePreferences,
    sharedTextForScreen: String?,
    onSharedTextConsumed: () -> Unit,
    modifier: Modifier = Modifier,
    navController: NavHostController = rememberNavController()
) {
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route

    val isOnTabScreen = currentRoute in Routes.TABS

    val scrollConnection = rememberFloatingTabBarScrollConnection()
    val hazeState = rememberHazeState()

    val authViewModel = LocalAuthViewModel.current
    val authState by authViewModel.state.collectAsState()
    val notificationRepository = remember { NotificationRepository(ApiClient.client) }
    var unreadCount by remember { mutableIntStateOf(0) }

    val haptic = LocalHapticFeedback.current

    LaunchedEffect(sharedTextForScreen) {
        if (sharedTextForScreen != null) {
            navController.navigate(Routes.SHARE) {
                launchSingleTop = true
            }
        }
    }

    LaunchedEffect(authState.isLoggedIn, currentRoute) {
        if (authState.isLoggedIn) {
            notificationRepository.getNotifications(limit = 1).onSuccess {
                unreadCount = it.unreadCount
            }
        } else {
            unreadCount = 0
        }
    }

    fun navigateToTab(route: String) {
        haptic.performHapticFeedback(HapticFeedbackType.TextHandleMove)
        navController.navigate(route) {
            popUpTo(navController.graph.findStartDestination().id) {
                saveState = true
            }
            launchSingleTop = true
            restoreState = true
        }
    }

    // Set before navigating rather than from inside the detail screen, so the list card already
    // carries its shared bounds on the frame the transition starts.
    var sharedEntryId by remember { mutableStateOf<String?>(null) }

    fun navigateToEntry(hashId: String) {
        sharedEntryId = hashId
        navController.navigate(Routes.entryDetail(hashId))
    }

    Box(modifier = modifier.fillMaxSize()) {
        CompositionLocalProvider(LocalSharedEntryId provides sharedEntryId) {
            @OptIn(ExperimentalSharedTransitionApi::class)
            SharedTransitionLayout {
                NavHost(
                    navController = navController,
                    startDestination = Routes.HOME,
                    enterTransition = { TrailMotion.enter(targetState) },
                    exitTransition = { TrailMotion.exit(targetState) },
                    popEnterTransition = { TrailMotion.popEnter(initialState) },
                    popExitTransition = { TrailMotion.popExit(initialState) },
                    predictivePopEnterTransition = { TrailMotion.popEnter(initialState) },
                    predictivePopExitTransition = { TrailMotion.popExit(initialState) },
                    modifier = Modifier
                        .fillMaxSize()
                        .hazeSource(state = hazeState)
                ) {
                    screen(Routes.HOME, this@SharedTransitionLayout) {
                        HomeScreen(
                            onNavigateToEntry = ::navigateToEntry,
                            onNavigateToUser = { nickname ->
                                navController.navigate(Routes.userProfile(nickname))
                            },
                            onNavigateToCollection = { slug ->
                                navController.navigate(Routes.collection(slug))
                            },
                            onNavigateToSearch = { query ->
                                navController.navigate(Routes.search(query))
                            },
                            scrollConnection = scrollConnection
                        )
                    }

                    screen(Routes.MY_FEED, this@SharedTransitionLayout) {
                        MyFeedScreen(
                            onNavigateToEntry = ::navigateToEntry,
                            onNavigateToUser = { nickname ->
                                navController.navigate(Routes.userProfile(nickname))
                            },
                            onNavigateToCollection = { slug ->
                                navController.navigate(Routes.collection(slug))
                            },
                            onNavigateToSearch = { query ->
                                navController.navigate(Routes.search(query))
                            },
                            scrollConnection = scrollConnection
                        )
                    }

                    screen(Routes.PROFILE, this@SharedTransitionLayout) {
                        ProfileScreen(
                            themePreferences = themePreferences,
                            onNavigateToEntry = ::navigateToEntry,
                            scrollConnection = scrollConnection
                        )
                    }

                    screen(
                        route = Routes.SEARCH,
                        sharedTransitionScope = this@SharedTransitionLayout,
                        arguments = listOf(navArgument("query") {
                            type = NavType.StringType; defaultValue = ""
                        })
                    ) { backStackEntry ->
                        SearchScreen(
                            initialQuery = backStackEntry.arguments?.getString("query") ?: "",
                            onNavigateToEntry = ::navigateToEntry,
                            onNavigateToUser = { nickname ->
                                navController.navigate(Routes.userProfile(nickname))
                            },
                            onNavigateToCollection = { slug ->
                                navController.navigate(Routes.collection(slug))
                            },
                            scrollConnection = scrollConnection
                        )
                    }

                    screen(
                        route = Routes.ENTRY_DETAIL,
                        sharedTransitionScope = this@SharedTransitionLayout,
                        arguments = listOf(navArgument("hashId") { type = NavType.StringType })
                    ) { backStackEntry ->
                        val hashId = backStackEntry.arguments?.getString("hashId") ?: return@screen
                        // Re-arms the shared element when this screen comes back off the back stack,
                        // and disarms it once the screen is gone for good. onDispose runs after the pop
                        // transition, so the collapsing card keeps its bounds for the whole animation.
                        DisposableEffect(hashId) {
                            sharedEntryId = hashId
                            onDispose { if (sharedEntryId == hashId) sharedEntryId = null }
                        }
                        EntryDetailScreen(
                            hashId = hashId,
                            hazeState = hazeState,
                            onNavigateBack = { navController.popBackStack() },
                            onNavigateToUser = { nickname ->
                                navController.navigate(Routes.userProfile(nickname))
                            }
                        )
                    }

                    screen(
                        route = Routes.USER_PROFILE,
                        sharedTransitionScope = this@SharedTransitionLayout,
                        arguments = listOf(navArgument("nickname") { type = NavType.StringType })
                    ) { backStackEntry ->
                        val nickname = backStackEntry.arguments?.getString("nickname") ?: return@screen
                        UserProfileScreen(
                            nickname = nickname,
                            hazeState = hazeState,
                            onNavigateBack = { navController.popBackStack() },
                            onNavigateToEntry = ::navigateToEntry,
                            onNavigateToUser = { nick ->
                                navController.navigate(Routes.userProfile(nick))
                            },
                            onNavigateToCollection = { slug ->
                                navController.navigate(Routes.collection(slug))
                            }
                        )
                    }

                    screen(
                        route = Routes.COLLECTION,
                        sharedTransitionScope = this@SharedTransitionLayout,
                        arguments = listOf(navArgument("slug") { type = NavType.StringType })
                    ) { backStackEntry ->
                        val slug = backStackEntry.arguments?.getString("slug") ?: return@screen
                        CollectionScreen(
                            slug = slug,
                            hazeState = hazeState,
                            onNavigateBack = { navController.popBackStack() },
                            onNavigateToEntry = ::navigateToEntry,
                            onNavigateToUser = { nickname ->
                                navController.navigate(Routes.userProfile(nickname))
                            }
                        )
                    }

                    screen(Routes.NOTIFICATIONS, this@SharedTransitionLayout) {
                        NotificationsScreen(
                            hazeState = hazeState,
                            onNavigateToEntry = ::navigateToEntry,
                            onNavigateToUser = { nickname ->
                                navController.navigate(Routes.userProfile(nickname))
                            }
                        )
                    }

                    screen(Routes.SHARE, this@SharedTransitionLayout) {
                        ShareScreen(
                            initialText = sharedTextForScreen ?: "",
                            onShareSuccess = {
                                onSharedTextConsumed()
                                navController.navigate(Routes.MY_FEED) {
                                    popUpTo(navController.graph.findStartDestination().id) {
                                        inclusive = false
                                    }
                                    launchSingleTop = true
                                    restoreState = false
                                }
                            },
                            onBack = {
                                onSharedTextConsumed()
                                navController.popBackStack()
                            }
                        )
                    }
                }
            }
        }

        AnimatedVisibility(
            visible = isOnTabScreen,
            enter = TrailMotion.tabBarEnter,
            exit = TrailMotion.tabBarExit,
            modifier = Modifier.align(Alignment.BottomCenter)
        ) {
            val isCompact = LocalWindowSizeClass.current.isCompactWidth
            Box(
                modifier = Modifier
                    .navigationBarsPadding()
                    .padding(
                        horizontal = if (isCompact) 16.dp else 24.dp,
                        vertical = if (isCompact) 8.dp else 4.dp
                    )
            ) {
                FloatingTabBar(
                    selectedTabKey = currentRoute ?: Routes.HOME,
                    scrollConnection = scrollConnection,
                    contentKey = Triple(currentRoute, authState.isLoggedIn, unreadCount),
                    tabBarContentModifier = Modifier.hazeEffect(state = hazeState),
                    colors = FloatingTabBarDefaults.colors(
                        backgroundColor = MaterialTheme.colorScheme.surfaceContainerHigh.copy(alpha = 0.6f)
                    )
                ) {
                    tab(
                        key = Routes.HOME,
                        title = {
                            Text(
                                "Home",
                                style = MaterialTheme.typography.labelSmall,
                                fontSize = 10.sp
                            )
                        },
                        icon = {
                            val tabBounce by animateDpAsState(
                                targetValue = if (currentRoute == Routes.HOME) (-2).dp else 0.dp,
                                animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
                                label = "homeBounce"
                            )
                            Box(modifier = Modifier.offset(y = tabBounce)) {
                                FaIcon(
                                    faIcon = FaIcons.Home,
                                    size = 20.dp,
                                    tint = if (currentRoute == Routes.HOME)
                                        MaterialTheme.colorScheme.primary
                                    else
                                        MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        },
                        onClick = { navigateToTab(Routes.HOME) }
                    )
                    tab(
                        key = Routes.MY_FEED,
                        title = {
                            Text(
                                "My Feed",
                                style = MaterialTheme.typography.labelSmall,
                                fontSize = 10.sp
                            )
                        },
                        icon = {
                            val tabBounce by animateDpAsState(
                                targetValue = if (currentRoute == Routes.MY_FEED) (-2).dp else 0.dp,
                                animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
                                label = "feedBounce"
                            )
                            Box(modifier = Modifier.offset(y = tabBounce)) {
                                FaIcon(
                                    faIcon = FaIcons.User,
                                    size = 20.dp,
                                    tint = if (currentRoute == Routes.MY_FEED)
                                        MaterialTheme.colorScheme.primary
                                    else
                                        MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        },
                        onClick = { navigateToTab(Routes.MY_FEED) }
                    )
                    tab(
                        key = Routes.PROFILE,
                        title = {
                            Text(
                                "Profile",
                                style = MaterialTheme.typography.labelSmall,
                                fontSize = 10.sp
                            )
                        },
                        icon = {
                            val tabBounce by animateDpAsState(
                                targetValue = if (currentRoute == Routes.PROFILE) (-2).dp else 0.dp,
                                animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
                                label = "profileBounce"
                            )
                            Box(modifier = Modifier.offset(y = tabBounce)) {
                                FaIcon(
                                    faIcon = FaIcons.IdCard,
                                    size = 20.dp,
                                    tint = if (currentRoute == Routes.PROFILE)
                                        MaterialTheme.colorScheme.primary
                                    else
                                        MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        },
                        onClick = { navigateToTab(Routes.PROFILE) }
                    )
                    if (authState.isLoggedIn) {
                        tab(
                            key = Routes.NOTIFICATIONS,
                            title = {
                                Text(
                                    "Alerts",
                                    style = MaterialTheme.typography.labelSmall,
                                    fontSize = 10.sp
                                )
                            },
                            icon = {
                                val isSelected = currentRoute == Routes.NOTIFICATIONS
                                val tabBounce by animateDpAsState(
                                    targetValue = if (isSelected) (-2).dp else 0.dp,
                                    animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
                                    label = "alertsBounce"
                                )
                                Box(
                                    modifier = Modifier
                                        .offset(y = tabBounce)
                                        .padding(top = 2.dp, end = 4.dp)
                                ) {
                                    FaIcon(
                                        faIcon = if (isSelected || unreadCount > 0) FaIcons.Bell else FaIcons.BellRegular,
                                        size = 20.dp,
                                        tint = if (isSelected)
                                            MaterialTheme.colorScheme.primary
                                        else
                                            MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                    if (unreadCount > 0) {
                                        val badgeScale by animateFloatAsState(
                                            targetValue = if (unreadCount > 0) 1f else 0f,
                                            animationSpec = spring(
                                                dampingRatio = Spring.DampingRatioMediumBouncy,
                                                stiffness = Spring.StiffnessMedium
                                            ),
                                            label = "badgeScale"
                                        )
                                        Box(
                                            modifier = Modifier
                                                .align(Alignment.TopEnd)
                                                .graphicsLayer {
                                                    scaleX = badgeScale
                                                    scaleY = badgeScale
                                                }
                                                .size(8.dp)
                                                .background(
                                                    MaterialTheme.colorScheme.error,
                                                    CircleShape
                                                )
                                        )
                                    }
                                }
                            },
                            onClick = { navigateToTab(Routes.NOTIFICATIONS) }
                        )
                    }
                    standaloneTab(
                        key = Routes.SEARCH,
                        icon = {
                            val searchSelected = currentRoute?.startsWith("search") == true
                            val tabBounce by animateDpAsState(
                                targetValue = if (searchSelected) (-2).dp else 0.dp,
                                animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
                                label = "searchBounce"
                            )
                            Box(modifier = Modifier.offset(y = tabBounce)) {
                                FaIcon(
                                    faIcon = FaIcons.Search,
                                    size = 20.dp,
                                    tint = if (searchSelected)
                                        MaterialTheme.colorScheme.primary
                                    else
                                        MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        },
                        onClick = { navigateToTab(Routes.search()) }
                    )
                }
            }
        }
    }
}
