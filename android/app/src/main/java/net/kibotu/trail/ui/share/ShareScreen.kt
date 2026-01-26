package net.kibotu.trail.ui.share

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import net.kibotu.trail.R
import org.koin.androidx.compose.koinViewModel

@Composable
fun ShareScreen(
    sharedUrl: String?,
    onSuccess: () -> Unit,
    onCancel: () -> Unit,
    viewModel: ShareViewModel = koinViewModel()
) {
    val shareState by viewModel.shareState.collectAsState()
    val text by viewModel.text.collectAsState()
    
    LaunchedEffect(sharedUrl) {
        if (!sharedUrl.isNullOrBlank()) {
            viewModel.setSharedUrl(sharedUrl)
        }
    }
    
    LaunchedEffect(shareState) {
        if (shareState is ShareState.Success) {
            onSuccess()
        }
    }
    
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        Text(
            text = stringResource(R.string.share_link),
            style = MaterialTheme.typography.headlineMedium
        )
        
        OutlinedTextField(
            value = text,
            onValueChange = { viewModel.setText(it) },
            label = { Text("Text (max 280 characters)") },
            modifier = Modifier.fillMaxWidth(),
            maxLines = 8,
            supportingText = {
                Text("${text.length}/280 - URLs and emojis are supported")
            },
            placeholder = {
                Text("Share your thoughts with a link! https://example.com 🎉")
            }
        )
        
        when (shareState) {
            is ShareState.Loading -> {
                CircularProgressIndicator()
            }
            is ShareState.Error -> {
                Text(
                    text = (shareState as ShareState.Error).message,
                    color = MaterialTheme.colorScheme.error
                )
            }
            else -> {}
        }
        
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            OutlinedButton(
                onClick = onCancel,
                modifier = Modifier.weight(1f)
            ) {
                Text("Cancel")
            }
            
            Button(
                onClick = { viewModel.shareEntry() },
                modifier = Modifier.weight(1f),
                enabled = shareState !is ShareState.Loading && text.isNotBlank()
            ) {
                Text(stringResource(R.string.submit))
            }
        }
    }
}
