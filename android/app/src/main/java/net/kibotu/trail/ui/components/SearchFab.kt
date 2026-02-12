package net.kibotu.trail.ui.components

import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.FloatingActionButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons

/**
 * A reusable search FloatingActionButton with consistent Material3 styling.
 *
 * @param onClick Callback invoked when the FAB is clicked
 * @param modifier Modifier to be applied to the FAB
 */
@Composable
fun SearchFab(
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    FloatingActionButton(
        onClick = onClick,
        modifier = modifier,
        containerColor = MaterialTheme.colorScheme.primaryContainer,
        contentColor = MaterialTheme.colorScheme.onPrimaryContainer,
        elevation = FloatingActionButtonDefaults.elevation(
            defaultElevation = 6.dp,
            pressedElevation = 12.dp
        )
    ) {
        FaIcon(
            faIcon = FaIcons.Search,
            size = 24.dp,
            tint = MaterialTheme.colorScheme.onPrimaryContainer
        )
    }
}
