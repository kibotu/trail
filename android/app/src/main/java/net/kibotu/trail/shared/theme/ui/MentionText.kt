package net.kibotu.trail.shared.theme.ui

import androidx.compose.foundation.text.ClickableText
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.sp

private val mentionRegex = Regex("@([a-zA-Z0-9_.-]+)")

@Composable
fun MentionText(
    text: String,
    modifier: Modifier = Modifier,
    style: TextStyle = MaterialTheme.typography.bodyMedium,
    lineHeight: androidx.compose.ui.unit.TextUnit = 22.sp,
    onMentionClick: (String) -> Unit = {},
    onClick: () -> Unit = {}
) {
    val primaryColor = MaterialTheme.colorScheme.primary
    val textColor = MaterialTheme.colorScheme.onSurface

    val annotated = buildAnnotatedString {
        var lastIndex = 0
        mentionRegex.findAll(text).forEach { match ->
            append(text.substring(lastIndex, match.range.first))
            pushStringAnnotation("mention", match.groupValues[1])
            withStyle(SpanStyle(color = primaryColor, fontWeight = FontWeight.Medium)) {
                append(match.value)
            }
            pop()
            lastIndex = match.range.last + 1
        }
        append(text.substring(lastIndex))
    }

    val hasMentions = mentionRegex.containsMatchIn(text)

    if (hasMentions) {
        ClickableText(
            text = annotated,
            modifier = modifier,
            style = style.copy(color = textColor, lineHeight = lineHeight),
            onClick = { offset ->
                val annotation = annotated.getStringAnnotations("mention", offset, offset).firstOrNull()
                if (annotation != null) {
                    onMentionClick(annotation.item)
                } else {
                    onClick()
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
