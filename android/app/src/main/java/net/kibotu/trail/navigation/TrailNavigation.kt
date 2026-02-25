package net.kibotu.trail.navigation

import android.os.Build
import androidx.annotation.RequiresApi
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
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
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import net.kibotu.trail.feature.entrydetail.EntryDetailScreen
import net.kibotu.trail.feature.home.HomeScreen
import net.kibotu.trail.feature.myfeed.MyFeedScreen
import net.kibotu.trail.feature.notifications.NotificationsScreen
import net.kibotu.trail.feature.profile.ProfileScreen
import net.kibotu.trail.feature.search.SearchScreen
import net.kibotu.trail.feature.userprofile.UserProfileScreen
import net.kibotu.trail.shared.storage.ThemePreferences
import net.kibotu.trail.shared.theme.ui.FloatingTabBar
import net.kibotu.trail.shared.theme.ui.rememberFloatingTabBarScrollConnection

object Routes {
    const val HOME = "home"
    const val MY_FEED = "my_feed"
    const val PROFILE = "profile"
    const val SEARCH = "search"
    const val ENTRY_DETAIL = "entry/{hashId}"
    const val USER_PROFILE = "user/{nickname}"
    const val NOTIFICATIONS = "notifications"

    fun entryDetail(hashId: String) = "entry/$hashId"
    fun userProfile(nickname: String) = "user/$nickname"
}

@RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
@Composable
fun TrailNavigation(
    themePreferences: ThemePreferences,
    modifier: Modifier = Modifier,
    navController: NavHostController = rememberNavController()
) {
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route

    val tabRoutes = listOf(Routes.HOME, Routes.MY_FEED, Routes.PROFILE, Routes.SEARCH)
    val isOnTabScreen = currentRoute in tabRoutes

    val scrollConnection = rememberFloatingTabBarScrollConnection()

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
            modifier = Modifier.fillMaxSize()
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
                        navController.navigate(Routes.SEARCH)
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

            composable(Routes.SEARCH) {
                SearchScreen(
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
                    onNavigateBack = { navController.popBackStack() },
                    onNavigateToEntry = { hashId ->
                        navController.navigate(Routes.entryDetail(hashId))
                    }
                )
            }

            composable(Routes.NOTIFICATIONS) {
                NotificationsScreen(
                    onNavigateBack = { navController.popBackStack() },
                    onNavigateToEntry = { hashId ->
                        navController.navigate(Routes.entryDetail(hashId))
                    },
                    onNavigateToUser = { nickname ->
                        navController.navigate(Routes.userProfile(nickname))
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
                    contentKey = currentRoute
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
                    standaloneTab(
                        key = Routes.SEARCH,
                        icon = {
                            FaIcon(
                                faIcon = FaIcons.Search,
                                size = 20.dp,
                                tint = if (currentRoute == Routes.SEARCH)
                                    MaterialTheme.colorScheme.primary
                                else
                                    MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        },
                        onClick = { navigateToTab(Routes.SEARCH) }
                    )
                }
            }
        }
    }
}
