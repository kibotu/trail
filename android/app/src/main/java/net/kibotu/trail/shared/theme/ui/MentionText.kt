package net.kibotu.trail.shared.theme.ui

import androidx.compose.foundation.text.ClickableText
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
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

    val annotated = buildAnnotatedString {
        var lastIndex = 0
        segments.forEach { segment ->
            append(text.substring(lastIndex, segment.start))
            pushStringAnnotation(segment.tag, segment.value)
            val spanStyle = when (segment.tag) {
                "mention" -> SpanStyle(color = primaryColor, fontWeight = FontWeight.Medium)
                "url" -> SpanStyle(color = primaryColor)
                else -> SpanStyle()
            }
            withStyle(spanStyle) {
                append(text.substring(segment.start, segment.end))
            }
            pop()
            lastIndex = segment.end
        }
        append(text.substring(lastIndex))
    }

    if (hasAnnotations) {
        ClickableText(
            text = annotated,
            modifier = modifier,
            style = style.copy(color = textColor, lineHeight = lineHeight),
            onClick = { offset ->
                val mention = annotated.getStringAnnotations("mention", offset, offset).firstOrNull()
                val url = annotated.getStringAnnotations("url", offset, offset).firstOrNull()
                when {
                    mention != null -> onMentionClick(mention.item)
                    url != null -> context.openInCustomTab(url.item)
                    else -> onClick()
                }
            }
        )
    } else {
        androidx.compose.material3.Text(
            text = text,
            modifier = modifier,
            style = style,
            color = textColor,
            lineHeight = lineHeight
        )
    }
}
