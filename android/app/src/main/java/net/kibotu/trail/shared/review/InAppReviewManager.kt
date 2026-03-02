package net.kibotu.trail.shared.review

import android.app.Activity
import android.content.Context
import androidx.compose.runtime.compositionLocalOf
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.google.android.play.core.review.ReviewManagerFactory
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.tasks.await

private val Context.reviewDataStore: DataStore<Preferences> by preferencesDataStore(name = "review_prefs")

class InAppReviewManager(private val context: Context) {

    private val reviewManager = ReviewManagerFactory.create(context)

    private val lastPromptTimestamp = context.reviewDataStore.data.map { prefs ->
        prefs[KEY_LAST_PROMPT_TIMESTAMP] ?: 0L
    }

    suspend fun shouldPrompt(): Boolean {
        val timestamp = lastPromptTimestamp.first()
        if (timestamp == 0L) return true
        val elapsed = System.currentTimeMillis() - timestamp
        return elapsed >= PROMPT_COOLDOWN_MS
    }

    suspend fun promptIfEligible(activity: Activity) {
        if (!shouldPrompt()) return
        try {
            val reviewInfo = reviewManager.requestReviewFlow().await()
            recordPromptAttempt()
            reviewManager.launchReviewFlow(activity, reviewInfo).await()
        } catch (_: Exception) {
            // Never disrupt the user experience over a review prompt failure
        }
    }

    private suspend fun recordPromptAttempt() {
        context.reviewDataStore.edit { prefs ->
            prefs[KEY_LAST_PROMPT_TIMESTAMP] = System.currentTimeMillis()
        }
    }

    companion object {
        private val KEY_LAST_PROMPT_TIMESTAMP = longPreferencesKey("last_review_prompt_timestamp")
        private const val PROMPT_COOLDOWN_MS = 7L * 24 * 60 * 60 * 1000 // 7 days
    }
}

val LocalInAppReviewManager = compositionLocalOf<InAppReviewManager> {
    error("InAppReviewManager not provided")
}
