package net.kibotu.trail.data.repository

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import net.kibotu.trail.data.api.TrailApiService
import net.kibotu.trail.data.model.*

val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "trail_prefs")

class TrailRepository(
    private val context: Context,
    private val apiService: TrailApiService
) {
    private val JWT_KEY = stringPreferencesKey("jwt_token")
    private val USER_ID_KEY = stringPreferencesKey("user_id")
    private val USER_EMAIL_KEY = stringPreferencesKey("user_email")
    private val USER_NAME_KEY = stringPreferencesKey("user_name")
    private val USER_AVATAR_KEY = stringPreferencesKey("user_avatar")
    
    val isLoggedIn: Flow<Boolean> = context.dataStore.data.map { preferences ->
        preferences[JWT_KEY] != null
    }
    
    val jwtToken: Flow<String?> = context.dataStore.data.map { preferences ->
        preferences[JWT_KEY]
    }
    
    suspend fun getJwtToken(): String? {
        return jwtToken.first()
    }
    
    suspend fun authenticateWithGoogle(googleToken: String): Result<AuthResponse> {
        val result = apiService.authenticateWithGoogle(googleToken)
        
        if (result.isSuccess) {
            val authResponse = result.getOrNull()!!
            saveAuthData(authResponse)
        }
        
        return result
    }
    
    suspend fun saveDevAuthData(authResponse: AuthResponse): Result<Unit> {
        return try {
            saveAuthData(authResponse)
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    private suspend fun saveAuthData(authResponse: AuthResponse) {
        context.dataStore.edit { preferences ->
            preferences[JWT_KEY] = authResponse.jwt
            preferences[USER_ID_KEY] = authResponse.user.id.toString()
            preferences[USER_EMAIL_KEY] = authResponse.user.email
            preferences[USER_NAME_KEY] = authResponse.user.name
            preferences[USER_AVATAR_KEY] = authResponse.user.gravatarUrl
        }
    }
    
    suspend fun logout() {
        context.dataStore.edit { preferences ->
            preferences.clear()
        }
    }
    
    suspend fun createEntry(text: String): Result<CreateEntryResponse> {
        return apiService.createEntry(text)
    }
    
    suspend fun getEntries(page: Int = 1, limit: Int = 20): Result<EntriesResponse> {
        return apiService.getEntries(page, limit)
    }
}
