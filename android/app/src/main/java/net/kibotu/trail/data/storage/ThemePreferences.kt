package net.kibotu.trail.data.storage

import android.content.Context
import android.content.SharedPreferences
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * Manages theme preferences for the app
 */
class ThemePreferences(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences(
        PREFS_NAME,
        Context.MODE_PRIVATE
    )

    private val _isDarkTheme = MutableStateFlow(getDarkTheme())
    val isDarkTheme: StateFlow<Boolean> = _isDarkTheme.asStateFlow()

    /**
     * Get the current theme preference
     * @return true for dark theme, false for light theme
     */
    fun getDarkTheme(): Boolean {
        return prefs.getBoolean(KEY_DARK_THEME, true) // Default to dark theme
    }

    /**
     * Set the theme preference
     * @param isDark true for dark theme, false for light theme
     */
    fun setDarkTheme(isDark: Boolean) {
        prefs.edit().putBoolean(KEY_DARK_THEME, isDark).apply()
        _isDarkTheme.value = isDark
    }

    /**
     * Toggle between dark and light theme
     */
    fun toggleTheme() {
        val newTheme = !getDarkTheme()
        setDarkTheme(newTheme)
    }

    companion object {
        private const val PREFS_NAME = "theme_prefs"
        private const val KEY_DARK_THEME = "dark_theme"
    }
}
