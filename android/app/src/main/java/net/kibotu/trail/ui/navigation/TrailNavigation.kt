package net.kibotu.trail.ui.navigation

import android.os.Build
import androidx.annotation.RequiresApi
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.windowInsetsPadding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.RssFeed
import androidx.compose.material.icons.outlined.Home
import androidx.compose.material.icons.outlined.Person
import androidx.compose.material.icons.outlined.RssFeed
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import kotlinx.serialization.Serializable
import net.kibotu.trail.data.storage.ThemePreferences
import net.kibotu.trail.ui.components.FloatingTabBar
import net.kibotu.trail.ui.components.rememberFloatingTabBarScrollConnection
import net.kibotu.trail.ui.screens.HomeScreen
import net.kibotu.trail.ui.screens.MyFeedScreen
import net.kibotu.trail.ui.screens.ProfileScreen
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
    val selectedIcon: ImageVector,
    val unselectedIcon: ImageVector
)

val tabs = listOf(
    TabItem(
        route = TabRoute.Home,
        title = "Home",
        selectedIcon = Icons.Filled.Home,
        unselectedIcon = Icons.Outlined.Home
    ),
    TabItem(
        route = TabRoute.MyFeed,
        title = "My Feed",
        selectedIcon = Icons.Filled.RssFeed,
        unselectedIcon = Icons.Outlined.RssFeed
    ),
    TabItem(
        route = TabRoute.Profile,
        title = "Profile",
        selectedIcon = Icons.Filled.Person,
        unselectedIcon = Icons.Outlined.Person
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
                    themePreferences = themePreferences,
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
                    Icon(
                        imageVector = if (selectedTabKey == "home") Icons.Filled.Home else Icons.Outlined.Home,
                        contentDescription = "Home"
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
                    Icon(
                        imageVector = if (selectedTabKey == "myfeed") Icons.Filled.RssFeed else Icons.Outlined.RssFeed,
                        contentDescription = "My Feed"
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
                    Icon(
                        imageVector = if (selectedTabKey == "profile") Icons.Filled.Person else Icons.Outlined.Person,
                        contentDescription = "Profile"
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
        }
    }
}
