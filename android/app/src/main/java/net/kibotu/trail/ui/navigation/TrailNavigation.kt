package net.kibotu.trail.ui.navigation

import android.os.Build
import androidx.annotation.RequiresApi
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.windowInsetsPadding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.compose.material3.LocalContentColor
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIconType
import com.guru.fontawesomecomposelib.FaIcons
import kotlinx.serialization.Serializable
import net.kibotu.trail.data.storage.ThemePreferences
import net.kibotu.trail.ui.components.FloatingTabBar
import net.kibotu.trail.ui.components.rememberFloatingTabBarScrollConnection
import net.kibotu.trail.ui.screens.HomeScreen
import net.kibotu.trail.ui.screens.MyFeedScreen
import net.kibotu.trail.ui.screens.ProfileScreen
import net.kibotu.trail.ui.viewmodel.SearchType
import net.kibotu.trail.ui.viewmodel.TrailViewModel

// Route definitions using type-safe navigation
@Serializable
sealed class TabRoute {
    @Serializable
    data object Home : TabRoute()

    @Serializable
    data object MyFeed : TabRoute()

    @Serializable
    data object Profile : TabRoute()
}

// Tab configuration
data class TabItem(
    val route: TabRoute,
    val title: String,
    val selectedIcon: FaIconType,
    val unselectedIcon: FaIconType
)

val tabs = listOf(
    TabItem(
        route = TabRoute.Home,
        title = "Home",
        selectedIcon = FaIcons.Home,
        unselectedIcon = FaIcons.Home
    ),
    TabItem(
        route = TabRoute.MyFeed,
        title = "My Feed",
        selectedIcon = FaIcons.Rss,
        unselectedIcon = FaIcons.Rss
    ),
    TabItem(
        route = TabRoute.Profile,
        title = "Profile",
        selectedIcon = FaIcons.User,
        unselectedIcon = FaIcons.User
    )
)

@RequiresApi(Build.VERSION_CODES.UPSIDE_DOWN_CAKE)
@Composable
fun TrailScaffold(
    viewModel: TrailViewModel,
    themePreferences: ThemePreferences,
    onLogout: () -> Unit
) {
    val navController = rememberNavController()
    var selectedTabKey by remember { mutableStateOf("home") }
    val scrollConnection = rememberFloatingTabBarScrollConnection()

    Box(modifier = Modifier.fillMaxSize()) {
        NavHost(
            navController = navController,
            startDestination = TabRoute.Home,
            modifier = Modifier.fillMaxSize()
        ) {
            composable<TabRoute.Home> {
                HomeScreen(
                    viewModel = viewModel,
                    scrollConnection = scrollConnection
                )
            }

            composable<TabRoute.MyFeed> {
                MyFeedScreen(
                    viewModel = viewModel,
                    scrollConnection = scrollConnection
                )
            }

            composable<TabRoute.Profile> {
                ProfileScreen(
                    viewModel = viewModel,
                    themePreferences = themePreferences,
                    onLogout = onLogout,
                    scrollConnection = scrollConnection
                )
            }
        }

        FloatingTabBar(
            selectedTabKey = selectedTabKey,
            scrollConnection = scrollConnection,
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .windowInsetsPadding(WindowInsets.navigationBars)
                .padding(start = 16.dp, end = 16.dp, top = 16.dp),
            colors = net.kibotu.trail.ui.components.FloatingTabBarDefaults.colors(
                backgroundColor = MaterialTheme.colorScheme.surfaceContainerHigh.copy(alpha = 0.7f)
            )
        ) {
            tab(
                key = "home",
                title = { Text("Home") },
                icon = {
                    FaIcon(
                        faIcon = FaIcons.Home,
                        size = 20.dp,
                        tint = LocalContentColor.current
                    )
                },
                onClick = {
                    selectedTabKey = "home"
                    navController.navigate(TabRoute.Home) {
                        popUpTo(navController.graph.findStartDestination().id) {
                            saveState = true
                        }
                        launchSingleTop = true
                        restoreState = true
                    }
                }
            )

            tab(
                key = "myfeed",
                title = { Text("My Feed") },
                icon = {
                    FaIcon(
                        faIcon = FaIcons.Rss,
                        size = 20.dp,
                        tint = LocalContentColor.current
                    )
                },
                onClick = {
                    selectedTabKey = "myfeed"
                    navController.navigate(TabRoute.MyFeed) {
                        popUpTo(navController.graph.findStartDestination().id) {
                            saveState = true
                        }
                        launchSingleTop = true
                        restoreState = true
                    }
                }
            )

            tab(
                key = "profile",
                title = { Text("Profile") },
                icon = {
                    FaIcon(
                        faIcon = FaIcons.User,
                        size = 20.dp,
                        tint = LocalContentColor.current
                    )
                },
                onClick = {
                    selectedTabKey = "profile"
                    navController.navigate(TabRoute.Profile) {
                        popUpTo(navController.graph.findStartDestination().id) {
                            saveState = true
                        }
                        launchSingleTop = true
                        restoreState = true
                    }
                }
            )

            // Search standalone tab
            standaloneTab(
                key = "search",
                icon = {
                    FaIcon(
                        faIcon = FaIcons.Search,
                        size = 20.dp,
                        tint = LocalContentColor.current
                    )
                },
                onClick = {
                    // Open search based on current tab
                    val searchType = when (selectedTabKey) {
                        "myfeed" -> SearchType.MY_FEED
                        else -> SearchType.HOME
                    }
                    viewModel.openSearch(searchType)
                }
            )
        }
    }
}
