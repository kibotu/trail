package net.kibotu.trail.shared.theme.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.text.BasicText
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.LinkAnnotation
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextLinkStyles
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withLink
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.sp
import net.kibotu.trail.shared.util.openInCustomTab

private val mentionRegex = Regex("@([a-zA-Z0-9_.-]+)")
private val urlRegex = Regex("https?://[\\w\\-._~:/?#\\[\\]@!$&'()*+,;=%]+", RegexOption.IGNORE_CASE)

private data class TextSegment(val start: Int, val end: Int, val tag: String, val value: String)

@Composable
fun MentionText(
    text: String,
    modifier: Modifier = Modifier,
    style: TextStyle = MaterialTheme.typography.bodyMedium,
    lineHeight: androidx.compose.ui.unit.TextUnit = 22.sp,
    onMentionClick: (String) -> Unit = {},
    onClick: () -> Unit = {}
) {
    val context = LocalContext.current
    val primaryColor = MaterialTheme.colorScheme.primary
    val textColor = MaterialTheme.colorScheme.onSurface

    val segments = mutableListOf<TextSegment>()
    mentionRegex.findAll(text).forEach { match ->
        segments.add(TextSegment(match.range.first, match.range.last + 1, "mention", match.groupValues[1]))
    }
    urlRegex.findAll(text).forEach { match ->
        val overlaps = segments.any { it.start < match.range.last + 1 && match.range.first < it.end }
        if (!overlaps) {
            segments.add(TextSegment(match.range.first, match.range.last + 1, "url", match.value))
        }
    }
    segments.sortBy { it.start }

    val hasAnnotations = segments.isNotEmpty()

    if (hasAnnotations) {
        val annotated = buildAnnotatedString {
            var lastIndex = 0
            segments.forEach { segment ->
                append(text.substring(lastIndex, segment.start))
                when (segment.tag) {
                    "mention" -> {
                        val nickname = segment.value
                        withLink(
                            LinkAnnotation.Clickable(
                                tag = "mention",
                                styles = TextLinkStyles(
                                    style = SpanStyle(color = primaryColor, fontWeight = FontWeight.Medium)
                                ),
                                linkInteractionListener = { onMentionClick(nickname) }
                            )
                        ) {
                            append(text.substring(segment.start, segment.end))
                        }
                    }
                    "url" -> {
                        val url = segment.value
                        withLink(
                            LinkAnnotation.Clickable(
                                tag = "url",
                                styles = TextLinkStyles(
                                    style = SpanStyle(color = primaryColor)
                                ),
                                linkInteractionListener = { context.openInCustomTab(url) }
                            )
                        ) {
                            append(text.substring(segment.start, segment.end))
                        }
                    }
                    else -> {
                        append(text.substring(segment.start, segment.end))
                    }
                }
                lastIndex = segment.end
            }
            append(text.substring(lastIndex))
        }
        BasicText(
            text = annotated,
            modifier = modifier.clickable(onClick = onClick),
            style = style.copy(color = textColor, lineHeight = lineHeight)
        )
    } else {
        androidx.compose.material3.Text(
            text = text,
            modifier = modifier.clickable(onClick = onClick),
            style = style,
            color = textColor,
            lineHeight = lineHeight
        )
    }
}
