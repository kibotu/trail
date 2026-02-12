package net.kibotu.trail.data.storage

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "trail_prefs")

class TokenManager(private val context: Context) {
    companion object {
        private val AUTH_TOKEN_KEY = stringPreferencesKey("auth_token")
        private val USER_EMAIL_KEY = stringPreferencesKey("user_email")
        private val USER_NAME_KEY = stringPreferencesKey("user_name")
        private val USER_ID_KEY = stringPreferencesKey("user_id")
        private val USER_NICKNAME_KEY = stringPreferencesKey("user_nickname")
        private val USER_PHOTO_URL_KEY = stringPreferencesKey("user_photo_url")
    }

    val authToken: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[AUTH_TOKEN_KEY]
    }

    val userEmail: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[USER_EMAIL_KEY]
    }

    val userName: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[USER_NAME_KEY]
    }

    val userId: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[USER_ID_KEY]
    }

    val userNickname: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[USER_NICKNAME_KEY]
    }

    val userPhotoUrl: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[USER_PHOTO_URL_KEY]
    }

    suspend fun saveAuthToken(
        token: String,
        email: String,
        name: String,
        userId: Int,
        nickname: String? = null,
        photoUrl: String? = null
    ) {
        context.dataStore.edit { preferences ->
            preferences[AUTH_TOKEN_KEY] = token
            preferences[USER_EMAIL_KEY] = email
            preferences[USER_NAME_KEY] = name
            preferences[USER_ID_KEY] = userId.toString()
            nickname?.let { preferences[USER_NICKNAME_KEY] = it }
            photoUrl?.let { preferences[USER_PHOTO_URL_KEY] = it }
        }
    }

    suspend fun getAuthToken(): String? {
        return authToken.first()
    }

    suspend fun clearAuthToken() {
        context.dataStore.edit { preferences ->
            preferences.remove(AUTH_TOKEN_KEY)
            preferences.remove(USER_EMAIL_KEY)
            preferences.remove(USER_NAME_KEY)
            preferences.remove(USER_ID_KEY)
            preferences.remove(USER_NICKNAME_KEY)
            preferences.remove(USER_PHOTO_URL_KEY)
        }
    }

    suspend fun isLoggedIn(): Boolean {
        return getAuthToken() != null
    }
}
