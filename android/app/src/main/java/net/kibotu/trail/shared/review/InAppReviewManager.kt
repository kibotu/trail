package net.kibotu.trail.shared.review

import android.app.Activity
import android.content.Context
import android.util.Log
import androidx.compose.runtime.compositionLocalOf
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.google.android.play.core.review.ReviewManagerFactory
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.tasks.await

private val Context.reviewDataStore: DataStore<Preferences> by preferencesDataStore(name = "review_prefs")

class InAppReviewManager(private val context: Context) {

    private val reviewManager = ReviewManagerFactory.create(context)

    suspend fun shouldPrompt(): Boolean {
        val prefs = context.reviewDataStore.data.first()
        if (prefs[KEY_REVIEW_COMPLETED] == true) {
            Log.d(TAG, "shouldPrompt: false (review previously completed)")
            return false
        }
        val timestamp = prefs[KEY_LAST_PROMPT_TIMESTAMP] ?: 0L
        if (timestamp == 0L) {
            Log.d(TAG, "shouldPrompt: true (first time)")
            return true
        }
        val elapsed = System.currentTimeMillis() - timestamp
        val eligible = elapsed >= PROMPT_COOLDOWN_MS
        Log.d(TAG, "shouldPrompt: $eligible (elapsed ${elapsed / 1000}s, cooldown ${PROMPT_COOLDOWN_MS / 1000}s)")
        return eligible
    }

    suspend fun promptIfEligible(activity: Activity) {
        if (!shouldPrompt()) return
        try {
            val reviewInfo = reviewManager.requestReviewFlow().await()
            Log.d(TAG, "requestReviewFlow succeeded, launching review dialog")
            reviewManager.launchReviewFlow(activity, reviewInfo).await()
            Log.d(TAG, "launchReviewFlow completed")
            recordPromptCompleted()
        } catch (e: Exception) {
            Log.w(TAG, "Review flow failed: ${e.message}", e)
        }
    }

    private suspend fun recordPromptCompleted() {
        context.reviewDataStore.edit { prefs ->
            prefs[KEY_LAST_PROMPT_TIMESTAMP] = System.currentTimeMillis()
            val count = (prefs[KEY_PROMPT_COUNT] ?: 0) + 1
            prefs[KEY_PROMPT_COUNT] = count
            // The Play API does not reveal whether the user actually submitted a review.
            // After a reasonable number of completed flows we assume the user has rated.
            if (count >= MAX_PROMPT_ATTEMPTS) {
                prefs[KEY_REVIEW_COMPLETED] = true
                Log.d(TAG, "Max prompt attempts ($MAX_PROMPT_ATTEMPTS) reached, marking review completed")
            }
        }
    }

    companion object {
        private const val TAG = "InAppReviewManager"
        private val KEY_LAST_PROMPT_TIMESTAMP = longPreferencesKey("last_review_prompt_timestamp")
        private val KEY_REVIEW_COMPLETED = booleanPreferencesKey("review_completed")
        private val KEY_PROMPT_COUNT = intPreferencesKey("review_prompt_count")
        private const val PROMPT_COOLDOWN_MS = 7L * 24 * 60 * 60 * 1000 // 1 week
        private const val MAX_PROMPT_ATTEMPTS = 3
    }
}

val LocalInAppReviewManager = compositionLocalOf<InAppReviewManager> {
    error("InAppReviewManager not provided")
}
