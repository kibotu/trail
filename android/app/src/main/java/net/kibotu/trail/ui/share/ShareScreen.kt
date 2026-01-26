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
    val url by viewModel.url.collectAsState()
    val message by viewModel.message.collectAsState()
    
    LaunchedEffect(sharedUrl) {
        if (!sharedUrl.isNullOrBlank()) {
            viewModel.setUrl(sharedUrl)
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
            value = url,
            onValueChange = { viewModel.setUrl(it) },
            label = { Text(stringResource(R.string.url)) },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            readOnly = !sharedUrl.isNullOrBlank()
        )
        
        OutlinedTextField(
            value = message,
            onValueChange = { viewModel.setMessage(it) },
            label = { Text(stringResource(R.string.message)) },
            modifier = Modifier.fillMaxWidth(),
            maxLines = 5,
            supportingText = {
                Text("${message.length}/280")
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
                enabled = shareState !is ShareState.Loading && url.isNotBlank() && message.isNotBlank()
            ) {
                Text(stringResource(R.string.submit))
            }
        }
    }
}
