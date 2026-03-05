package net.kibotu.trail.feature.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip

import androidx.compose.foundation.layout.widthIn
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil3.compose.AsyncImage
import com.guru.fontawesomecomposelib.FaIcon
import com.guru.fontawesomecomposelib.FaIcons
import kotlinx.coroutines.launch
import net.kibotu.trail.BuildConfig
import net.kibotu.trail.shared.network.ApiClient
import net.kibotu.trail.shared.profile.ProfileRepository
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit

@Composable
fun DeletionBlockerScreen(
    deletionRequestedAt: String?,
    onRevertSuccess: () -> Unit,
    onLogout: () -> Unit
) {
    val profileRepository = remember { ProfileRepository(ApiClient.client) }
    val scope = rememberCoroutineScope()
    var isReverting by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    val daysRemaining = remember(deletionRequestedAt) {
        try {
            val requestDate = LocalDate.parse(
                deletionRequestedAt?.take(10),
                DateTimeFormatter.ISO_LOCAL_DATE
            )
            val deleteDate = requestDate.plusDays(14)
            val remaining = ChronoUnit.DAYS.between(LocalDate.now(), deleteDate).toInt()
            remaining.coerceAtLeast(0)
        } catch (_: Exception) {
            null
        }
    }

    val formattedDate = remember(deletionRequestedAt) {
        try {
            val date = LocalDate.parse(
                deletionRequestedAt?.take(10),
                DateTimeFormatter.ISO_LOCAL_DATE
            )
            date.format(DateTimeFormatter.ofPattern("MMM d, yyyy"))
        } catch (_: Exception) {
            deletionRequestedAt?.take(10)
        }
    }

    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Card(
            modifier = Modifier
                .widthIn(max = 500.dp)
                .fillMaxWidth()
                .padding(24.dp),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surface
            ),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
        ) {
            Column(
                modifier = Modifier.verticalScroll(rememberScrollState())
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(MaterialTheme.colorScheme.surfaceContainerHighest)
                        .padding(top = 24.dp, bottom = 8.dp),
                    contentAlignment = Alignment.Center
                ) {
                    AsyncImage(
                        model = "${BuildConfig.API_BASE_URL}assets/undo-delete-whale.png",
                        contentDescription = "Changed your mind?",
                        modifier = Modifier
                            .fillMaxWidth(0.65f),
                        contentScale = ContentScale.FillWidth
                    )
                }

                Column(
                    modifier = Modifier.padding(horizontal = 24.dp, vertical = 20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        text = buildAnnotatedString {
                            append("You requested account deletion on ")
                            withStyle(SpanStyle(fontWeight = FontWeight.Bold)) {
                                append(formattedDate ?: "an unknown date")
                            }
                            append(". Your data will be permanently removed ")
                            withStyle(SpanStyle(fontWeight = FontWeight.Bold)) {
                                when {
                                    daysRemaining == null -> append("soon")
                                    daysRemaining == 0 -> append("soon")
                                    daysRemaining == 1 -> append("in 1 day")
                                    else -> append("in $daysRemaining days")
                                }
                            }
                            append(".")
                        },
                        style = MaterialTheme.typography.bodyMedium,
                        textAlign = TextAlign.Center,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        lineHeight = 22.sp
                    )

                    Spacer(modifier = Modifier.height(20.dp))

                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        InfoItem(
                            icon = FaIcons.EyeSlash,
                            text = "Your profile, entries, and comments are currently hidden from public view."
                        )
                        InfoItem(
                            icon = FaIcons.Clock,
                            text = "Permanent deletion occurs 14 days after your request."
                        )
                    }

                    Spacer(modifier = Modifier.height(24.dp))

                    if (error != null) {
                        Text(
                            error!!,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.error,
                            textAlign = TextAlign.Center
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                    }

                    Button(
                        onClick = {
                            isReverting = true
                            error = null
                            scope.launch {
                                profileRepository.revertDeletion().fold(
                                    onSuccess = { onRevertSuccess() },
                                    onFailure = {
                                        error = it.message ?: "Failed to revert deletion"
                                        isReverting = false
                                    }
                                )
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        enabled = !isReverting
                    ) {
                        if (isReverting) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(18.dp),
                                strokeWidth = 2.dp,
                                color = MaterialTheme.colorScheme.onPrimary
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Restoring your account\u2026")
                        } else {
                            FaIcon(FaIcons.Undo, size = 14.dp, tint = MaterialTheme.colorScheme.onPrimary)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Keep My Account")
                        }
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    OutlinedButton(
                        onClick = onLogout,
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.outlinedButtonColors(
                            contentColor = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    ) {
                        FaIcon(
                            FaIcons.SignOutAlt,
                            size = 14.dp,
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Log Out")
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    Text(
                        text = "You can also restore your account by emailing contact@kibotu.net",
                        style = MaterialTheme.typography.labelSmall,
                        textAlign = TextAlign.Center,
                        color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f),
                        lineHeight = 16.sp
                    )
                }
            }
        }
    }
}

@Composable
private fun InfoItem(icon: com.guru.fontawesomecomposelib.FaIconType, text: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.Top
    ) {
        FaIcon(
            icon,
            size = 14.dp,
            tint = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f),
            modifier = Modifier.padding(top = 2.dp)
        )
        Spacer(modifier = Modifier.width(12.dp))
        Text(
            text = text,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            lineHeight = 20.sp
        )
    }
}
