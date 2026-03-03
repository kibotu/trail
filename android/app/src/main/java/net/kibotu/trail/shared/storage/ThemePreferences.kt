package net.kibotu.trail.shared.storage

import android.content.Context
import androidx.compose.runtime.compositionLocalOf
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch

private val Context.themeDataStore: DataStore<Preferences> by preferencesDataStore(name = "theme_prefs")

class ThemePreferences(private val context: Context) {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    val isDarkTheme: StateFlow<Boolean>
        field = MutableStateFlow(true)

    val showEntryTags: StateFlow<Boolean>
        field = MutableStateFlow(true)

    init {
        scope.launch {
            context.themeDataStore.data.map { it[KEY_DARK_THEME] ?: true }.collect { isDarkTheme.value = it }
        }
        scope.launch {
            context.themeDataStore.data.map { it[KEY_SHOW_ENTRY_TAGS] ?: true }.collect { showEntryTags.value = it }
        }
    }

    fun setDarkTheme(isDark: Boolean) {
        isDarkTheme.value = isDark
        scope.launch { context.themeDataStore.edit { it[KEY_DARK_THEME] = isDark } }
    }

    fun toggleTheme() {
        setDarkTheme(!isDarkTheme.value)
    }

    fun setShowEntryTags(show: Boolean) {
        showEntryTags.value = show
        scope.launch { context.themeDataStore.edit { it[KEY_SHOW_ENTRY_TAGS] = show } }
    }

    companion object {
        private val KEY_DARK_THEME = booleanPreferencesKey("dark_theme")
        private val KEY_SHOW_ENTRY_TAGS = booleanPreferencesKey("show_entry_tags")
    }
}

val LocalThemePreferences = compositionLocalOf<ThemePreferences> {
    error("ThemePreferences not provided")
}
