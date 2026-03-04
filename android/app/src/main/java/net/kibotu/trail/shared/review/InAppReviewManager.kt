package net.kibotu.trail.shared.review

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
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
import kotlinx.coroutines.tasks.await

private val Context.reviewDataStore: DataStore<Preferences> by preferencesDataStore(name = "review_prefs")

class InAppReviewManager(private val context: Context) {

    suspend fun markHasPosted() {
        context.reviewDataStore.edit { prefs ->
            if (prefs[KEY_HAS_POSTED] != true) {
                prefs[KEY_HAS_POSTED] = true
                Log.d(TAG, "First successful post recorded")
            }
        }
    }

    suspend fun shouldPrompt(): Boolean {
        val prefs = context.reviewDataStore.data.first()

        if (prefs[KEY_REVIEW_COMPLETED] == true) {
            Log.d(TAG, "shouldPrompt: false (review marked completed)")
            return false
        }

        if (prefs[KEY_HAS_POSTED] != true) {
            Log.d(TAG, "shouldPrompt: false (user has never posted)")
            return false
        }

        val promptCount = prefs[KEY_PROMPT_COUNT] ?: 0
        if (promptCount >= MAX_PROMPT_ATTEMPTS) {
            context.reviewDataStore.edit { it[KEY_REVIEW_COMPLETED] = true }
            Log.d(TAG, "shouldPrompt: false (max attempts $MAX_PROMPT_ATTEMPTS reached, marking completed)")
            return false
        }

        val lastTimestamp = prefs[KEY_LAST_PROMPT_TIMESTAMP] ?: 0L
        if (lastTimestamp == 0L) {
            Log.d(TAG, "shouldPrompt: true (first prompt after posting)")
            return true
        }

        val elapsed = System.currentTimeMillis() - lastTimestamp
        val eligible = elapsed >= PROMPT_COOLDOWN_MS
        Log.d(TAG, "shouldPrompt: $eligible (elapsed ${elapsed / 1000}s, cooldown ${PROMPT_COOLDOWN_MS / 1000}s, attempt ${promptCount + 1}/$MAX_PROMPT_ATTEMPTS)")
        return eligible
    }

    /**
     * Creates a fresh ReviewManager from the Activity context each time to avoid stale
     * Play Store service connections. The Activity is also required by launchReviewFlow().
     */
    suspend fun promptIfEligible(activity: Activity) {
        if (!shouldPrompt()) return
        try {
            val reviewManager = ReviewManagerFactory.create(activity)
            val reviewInfo = reviewManager.requestReviewFlow().await()
            Log.d(TAG, "requestReviewFlow succeeded, launching review dialog")
            reviewManager.launchReviewFlow(activity, reviewInfo).await()
            Log.d(TAG, "launchReviewFlow completed")
            recordPromptAttempt()
        } catch (e: Exception) {
            Log.w(TAG, "Review flow failed: ${e.message}", e)
        }
    }

    private suspend fun recordPromptAttempt() {
        context.reviewDataStore.edit { prefs ->
            prefs[KEY_LAST_PROMPT_TIMESTAMP] = System.currentTimeMillis()
            val count = (prefs[KEY_PROMPT_COUNT] ?: 0) + 1
            prefs[KEY_PROMPT_COUNT] = count
            Log.d(TAG, "Prompt attempt #$count/$MAX_PROMPT_ATTEMPTS recorded")
        }
    }

    companion object {
        private const val TAG = "InAppReviewManager"
        private val KEY_HAS_POSTED = booleanPreferencesKey("has_posted")
        private val KEY_LAST_PROMPT_TIMESTAMP = longPreferencesKey("last_review_prompt_timestamp")
        private val KEY_REVIEW_COMPLETED = booleanPreferencesKey("review_completed")
        private val KEY_PROMPT_COUNT = intPreferencesKey("review_prompt_count")
        private const val PROMPT_COOLDOWN_MS = 7L * 24 * 60 * 60 * 1000 // 1 week
        private const val MAX_PROMPT_ATTEMPTS = 12
    }
}

fun Context.findActivity(): Activity? {
    var ctx: Context = this
    while (ctx is ContextWrapper) {
        if (ctx is Activity) return ctx
        ctx = ctx.baseContext
    }
    return null
}

val LocalInAppReviewManager = compositionLocalOf<InAppReviewManager> {
    error("InAppReviewManager not provided")
}
