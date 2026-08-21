package net.kibotu.trail.shared.profile

import android.content.Context
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.first
import kotlinx.serialization.json.Json

// Top-level delegate — one lazy DataStore per file, shared by every caller regardless of Context instance.
private val Context.profileStore by preferencesDataStore(name = "profile_cache")

private val KEY = stringPreferencesKey("profile")
private val json = Json { ignoreUnknownKeys = true }

/**
 * Persists the last successful [ProfileResponse] so the Profile tab can render without a network.
 */
class ProfileCache(context: Context) {

    // Always use applicationContext so the singleton DataStore delegate resolves to the same instance.
    private val appContext = context.applicationContext

    suspend fun save(profile: ProfileResponse) {
        appContext.profileStore.edit { preferences ->
            preferences[KEY] = json.encodeToString(ProfileResponse.serializer(), profile)
        }
    }

    suspend fun load(): ProfileResponse? {
        val cached = appContext.profileStore.data.first()[KEY]
        return cached?.let { json.decodeFromString(ProfileResponse.serializer(), it) }
    }

    suspend fun clear() {
        appContext.profileStore.edit { preferences -> preferences.remove(KEY) }
    }
}
