package net.kibotu.trail.shared.profile

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.first
import kotlinx.serialization.json.Json

/**
 * Persists the last successful [ProfileResponse] so the Profile tab can render without a network.
 *
 * The store is a process-wide singleton (DataStore requires one instance per file); the file lives
 * in the app's `datastore/` directory and is shared by every [ProfileCache] instance.
 */
class ProfileCache(private val context: Context) {

    companion object {
        // DataStore must be a process-wide singleton (one instance per file); the delegate lives in
        // the companion so every [ProfileCache] shares a single backing file.
        private val Context.profileStore: DataStore<Preferences> by preferencesDataStore(name = "profile_cache")
        private val KEY = stringPreferencesKey("profile")
        private val json = Json { ignoreUnknownKeys = true }
    }

    suspend fun save(profile: ProfileResponse) {
        context.profileStore.edit { preferences ->
            preferences[KEY] = json.encodeToString(ProfileResponse.serializer(), profile)
        }
    }

    suspend fun load(): ProfileResponse? {
        val cached = context.profileStore.data.first()[KEY]
        return cached?.let { json.decodeFromString(ProfileResponse.serializer(), it) }
    }

    suspend fun clear() {
        context.profileStore.edit { preferences -> preferences.remove(KEY) }
    }
}
