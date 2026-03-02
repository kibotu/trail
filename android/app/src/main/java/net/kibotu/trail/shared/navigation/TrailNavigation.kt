package net.kibotu.trail.shared.navigation

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
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import kotlinx.coroutines.flow.StateFlow
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import net.kibotu.trail.feature.auth.LocalAuthViewModel
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.notification.NotificationRepository
import net.kibotu.trail.feature.entrydetail.EntryDetailScreen
import net.kibotu.trail.feature.home.HomeScreen
import net.kibotu.trail.feature.myfeed.MyFeedScreen
import net.kibotu.trail.feature.notifications.NotificationsScreen
import net.kibotu.trail.feature.profile.ProfileScreen
import net.kibotu.trail.feature.search.SearchScreen
import net.kibotu.trail.feature.share.ShareScreen
import net.kibotu.trail.feature.userprofile.UserProfileScreen
import net.kibotu.trail.shared.storage.ThemePreferences
import net.kibotu.trail.shared.theme.ui.FloatingTabBar
import net.kibotu.trail.shared.theme.ui.FloatingTabBarDefaults
import net.kibotu.trail.shared.theme.ui.rememberFloatingTabBarScrollConnection
import dev.chrisbanes.haze.hazeEffect
import dev.chrisbanes.haze.hazeSource
import dev.chrisbanes.haze.rememberHazeState
import java.net.URLEncoder

object Routes {
    const val HOME = "home"
    const val MY_FEED = "my_feed"
    const val PROFILE = "profile"
    const val SEARCH = "search?query={query}"
    const val ENTRY_DETAIL = "entry/{hashId}"
    const val USER_PROFILE = "user/{nickname}"
    const val NOTIFICATIONS = "notifications"
    const val SHARE = "share"

    fun entryDetail(hashId: String) = "entry/$hashId"
    fun userProfile(nickname: String) = "user/$nickname"
    fun search(query: String = "") = if (query.isNotBlank()) "search?query=${URLEncoder.encode(query, "UTF-8")}" else "search"
}

@Composable
fun TrailNavigation(
    themePreferences: ThemePreferences,
    pendingSharedText: StateFlow<String?>,
    onConsumeSharedText: () -> Unit,
    modifier: Modifier = Modifier,
    navController: NavHostController = rememberNavController()
) {
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route

    val tabRoutes = listOf(Routes.HOME, Routes.MY_FEED, Routes.PROFILE, Routes.SEARCH, Routes.NOTIFICATIONS)
    val isOnTabScreen = currentRoute in tabRoutes || currentRoute?.startsWith("search") == true

    val scrollConnection = rememberFloatingTabBarScrollConnection()
    val hazeState = rememberHazeState()

    val authState by LocalAuthViewModel.current.state.collectAsState()
    val notificationRepository = remember { NotificationRepository(ApiClient.client) }
    var unreadCount by remember { mutableIntStateOf(0) }

    var sharedTextForScreen by rememberSaveable { mutableStateOf<String?>(null) }
    val pendingText by pendingSharedText.collectAsState()

    LaunchedEffect(pendingText) {
        pendingText?.let { text ->
            sharedTextForScreen = text
            onConsumeSharedText()
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
        navController.navigate(route) {
            popUpTo(navController.graph.findStartDestination().id) {
                saveState = true
            }
            launchSingleTop = true
            restoreState = true
        }
    }

    Box(modifier = modifier.fillMaxSize()) {
        NavHost(
            navController = navController,
            startDestination = Routes.HOME,
            modifier = Modifier
                .fillMaxSize()
                .hazeSource(state = hazeState)
        ) {
            composable(Routes.HOME) {
                HomeScreen(
                    onNavigateToEntry = { hashId ->
                        navController.navigate(Routes.entryDetail(hashId))
                    },
                    onNavigateToUser = { nickname ->
                        navController.navigate(Routes.userProfile(nickname))
                    },
                    onNavigateToSearch = { query ->
                        navController.navigate(Routes.search(query))
                    },
                    scrollConnection = scrollConnection
                )
            }

            composable(Routes.MY_FEED) {
                MyFeedScreen(
                    onNavigateToEntry = { hashId ->
                        navController.navigate(Routes.entryDetail(hashId))
                    },
                    onNavigateToUser = { nickname ->
                        navController.navigate(Routes.userProfile(nickname))
                    },
                    onNavigateToSearch = { query ->
                        navController.navigate(Routes.search(query))
                    },
                    scrollConnection = scrollConnection
                )
            }

            composable(Routes.PROFILE) {
                ProfileScreen(
                    themePreferences = themePreferences,
                    onNavigateToEntry = { hashId ->
                        navController.navigate(Routes.entryDetail(hashId))
                    },
                    scrollConnection = scrollConnection
                )
            }

            composable(
                route = Routes.SEARCH,
                arguments = listOf(navArgument("query") { type = NavType.StringType; defaultValue = "" })
            ) { backStackEntry ->
                val initialQuery = backStackEntry.arguments?.getString("query") ?: ""
                SearchScreen(
                    initialQuery = initialQuery,
                    onNavigateToEntry = { hashId ->
                        navController.navigate(Routes.entryDetail(hashId))
                    },
                    onNavigateToUser = { nickname ->
                        navController.navigate(Routes.userProfile(nickname))
                    },
                    scrollConnection = scrollConnection
                )
            }

            composable(
                route = Routes.ENTRY_DETAIL,
                arguments = listOf(navArgument("hashId") { type = NavType.StringType })
            ) { backStackEntry ->
                val hashId = backStackEntry.arguments?.getString("hashId") ?: return@composable
                EntryDetailScreen(
                    hashId = hashId,
                    hazeState = hazeState,
                    onNavigateBack = { navController.popBackStack() },
                    onNavigateToUser = { nickname ->
                        navController.navigate(Routes.userProfile(nickname))
                    }
                )
            }

            composable(
                route = Routes.USER_PROFILE,
                arguments = listOf(navArgument("nickname") { type = NavType.StringType })
            ) { backStackEntry ->
                val nickname = backStackEntry.arguments?.getString("nickname") ?: return@composable
                UserProfileScreen(
                    nickname = nickname,
                    hazeState = hazeState,
                    onNavigateBack = { navController.popBackStack() },
                    onNavigateToEntry = { hashId ->
                        navController.navigate(Routes.entryDetail(hashId))
                    }
                )
            }

            composable(Routes.NOTIFICATIONS) {
                NotificationsScreen(
                    hazeState = hazeState,
                    onNavigateToEntry = { hashId ->
                        navController.navigate(Routes.entryDetail(hashId))
                    },
                    onNavigateToUser = { nickname ->
                        navController.navigate(Routes.userProfile(nickname))
                    }
                )
            }

            composable(Routes.SHARE) {
                ShareScreen(
                    initialText = sharedTextForScreen ?: "",
                    onShareSuccess = {
                        sharedTextForScreen = null
                        navController.navigate(Routes.MY_FEED) {
                            popUpTo(navController.graph.findStartDestination().id) {
                                inclusive = false
                            }
                            launchSingleTop = true
                            restoreState = false
                        }
                    },
                    onBack = {
                        sharedTextForScreen = null
                        navController.popBackStack()
                    }
                )
            }
        }

        if (isOnTabScreen) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
                    .padding(horizontal = 16.dp, vertical = 8.dp)
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
                            FaIcon(
                                faIcon = FaIcons.Home,
                                size = 20.dp,
                                tint = if (currentRoute == Routes.HOME)
                                    MaterialTheme.colorScheme.primary
                                else
                                    MaterialTheme.colorScheme.onSurfaceVariant
                            )
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
                            FaIcon(
                                faIcon = FaIcons.User,
                                size = 20.dp,
                                tint = if (currentRoute == Routes.MY_FEED)
                                    MaterialTheme.colorScheme.primary
                                else
                                    MaterialTheme.colorScheme.onSurfaceVariant
                            )
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
                            FaIcon(
                                faIcon = FaIcons.IdCard,
                                size = 20.dp,
                                tint = if (currentRoute == Routes.PROFILE)
                                    MaterialTheme.colorScheme.primary
                                else
                                    MaterialTheme.colorScheme.onSurfaceVariant
                            )
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
                                Box {
                                    FaIcon(
                                        faIcon = if (isSelected || unreadCount > 0) FaIcons.Bell else FaIcons.BellRegular,
                                        size = 20.dp,
                                        tint = if (isSelected)
                                            MaterialTheme.colorScheme.primary
                                        else
                                            MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                    if (unreadCount > 0) {
                                        Box(
                                            modifier = Modifier
                                                .align(Alignment.TopEnd)
                                                .offset(x = 4.dp, y = (-2).dp)
                                                .size(8.dp)
                                                .background(MaterialTheme.colorScheme.error, CircleShape)
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
                            FaIcon(
                                faIcon = FaIcons.Search,
                                size = 20.dp,
                                tint = if (currentRoute?.startsWith("search") == true)
                                    MaterialTheme.colorScheme.primary
                                else
                                    MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        },
                        onClick = { navigateToTab(Routes.search()) }
                    )
                }
            }
        }
    }
}
