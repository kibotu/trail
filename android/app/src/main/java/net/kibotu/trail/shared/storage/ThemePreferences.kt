package net.kibotu.trail.shared.storage

import android.content.Context
import android.content.SharedPreferences
import androidx.compose.runtime.compositionLocalOf
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

class ThemePreferences(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences(
        PREFS_NAME,
        Context.MODE_PRIVATE
    )

    private val _isDarkTheme = MutableStateFlow(getDarkTheme())
    val isDarkTheme: StateFlow<Boolean> = _isDarkTheme.asStateFlow()

    private val _showEntryTags = MutableStateFlow(getShowEntryTags())
    val showEntryTags: StateFlow<Boolean> = _showEntryTags.asStateFlow()

    fun getDarkTheme(): Boolean {
        return prefs.getBoolean(KEY_DARK_THEME, true)
    }

    fun setDarkTheme(isDark: Boolean) {
        prefs.edit().putBoolean(KEY_DARK_THEME, isDark).apply()
        _isDarkTheme.value = isDark
    }

    fun toggleTheme() {
        val newTheme = !getDarkTheme()
        setDarkTheme(newTheme)
    }

    fun getShowEntryTags(): Boolean {
        return prefs.getBoolean(KEY_SHOW_ENTRY_TAGS, true)
    }

    fun setShowEntryTags(show: Boolean) {
        prefs.edit().putBoolean(KEY_SHOW_ENTRY_TAGS, show).apply()
        _showEntryTags.value = show
    }

    companion object {
        private const val PREFS_NAME = "theme_prefs"
        private const val KEY_DARK_THEME = "dark_theme"
        private const val KEY_SHOW_ENTRY_TAGS = "show_entry_tags"
    }
}

val LocalThemePreferences = compositionLocalOf<ThemePreferences> {
    error("ThemePreferences not provided")
}
