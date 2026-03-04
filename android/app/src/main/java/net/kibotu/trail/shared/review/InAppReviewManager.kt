package net.kibotu.trail.shared.review

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
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
import timber.log.Timber
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

private val Context.reviewDataStore: DataStore<Preferences> by preferencesDataStore(name = "review_prefs")

class InAppReviewManager(private val context: Context) {

    init {
        Timber.d("┌─── InAppReviewManager created ───")
        Timber.d("│ context type: %s", context.javaClass.simpleName)
        Timber.d("└──────────────────────────────────")
    }

    suspend fun markHasPosted() {
        Timber.d("markHasPosted() called")
        context.reviewDataStore.edit { prefs ->
            val alreadyPosted = prefs[KEY_HAS_POSTED] == true
            if (!alreadyPosted) {
                prefs[KEY_HAS_POSTED] = true
                Timber.d("✓ First successful post recorded (has_posted = true)")
            } else {
                Timber.d("· Already marked as posted, skipping")
            }
        }
    }

    suspend fun shouldPrompt(): Boolean {
        Timber.d("┌─── shouldPrompt() ───")
        val prefs = context.reviewDataStore.data.first()

        val hasPosted = prefs[KEY_HAS_POSTED] == true
        val reviewCompleted = prefs[KEY_REVIEW_COMPLETED] == true
        val promptCount = prefs[KEY_PROMPT_COUNT] ?: 0
        val lastTimestamp = prefs[KEY_LAST_PROMPT_TIMESTAMP] ?: 0L
        val lastDate = if (lastTimestamp > 0) DATE_FORMAT.format(Date(lastTimestamp)) else "never"

        Timber.d("│ hasPosted=%s", hasPosted)
        Timber.d("│ reviewCompleted=%s", reviewCompleted)
        Timber.d("│ promptCount=%d/%d", promptCount, MAX_PROMPT_ATTEMPTS)
        Timber.d("│ lastPrompt=%s (ts=%d)", lastDate, lastTimestamp)

        if (reviewCompleted) {
            Timber.d("└─→ false (review marked completed)")
            return false
        }

        if (!hasPosted) {
            Timber.d("└─→ false (user has never posted)")
            return false
        }

        if (promptCount >= MAX_PROMPT_ATTEMPTS) {
            context.reviewDataStore.edit { it[KEY_REVIEW_COMPLETED] = true }
            Timber.d("└─→ false (max attempts reached, now marking completed)")
            return false
        }

        if (lastTimestamp == 0L) {
            Timber.d("└─→ true (first prompt ever after posting)")
            return true
        }

        val elapsed = System.currentTimeMillis() - lastTimestamp
        val remaining = PROMPT_COOLDOWN_MS - elapsed
        val eligible = elapsed >= PROMPT_COOLDOWN_MS
        Timber.d("│ elapsed=%ds (%dh)", elapsed / 1000, elapsed / 3600_000)
        Timber.d("│ cooldown=%ds (%dh)", PROMPT_COOLDOWN_MS / 1000, PROMPT_COOLDOWN_MS / 3600_000)
        if (!eligible) {
            Timber.d("│ remaining=%ds (%dh)", remaining / 1000, remaining / 3600_000)
        }
        Timber.d("└─→ %s", eligible)
        return eligible
    }

    /**
     * Creates a fresh ReviewManager from the Activity context each time to avoid stale
     * Play Store service connections. The Activity is also required by launchReviewFlow().
     */
    suspend fun promptIfEligible(activity: Activity) {
        Timber.d("┌─── promptIfEligible() ───")
        Timber.d("│ activity: %s", activity.javaClass.simpleName)
        Timber.d("│ activity.isFinishing=%s", activity.isFinishing)
        Timber.d("│ activity.isDestroyed=%s", activity.isDestroyed)

        if (activity.isFinishing || activity.isDestroyed) {
            Timber.w("└─→ ABORT: activity is finishing or destroyed")
            return
        }

        if (!shouldPrompt()) {
            Timber.d("  (shouldPrompt returned false, skipping)")
            return
        }

        try {
            Timber.d("│ Creating ReviewManager with activity context...")
            val reviewManager = ReviewManagerFactory.create(activity)
            Timber.d("│ ReviewManager created: %s", reviewManager.javaClass.simpleName)

            Timber.d("│ Calling requestReviewFlow()...")
            val reviewInfo = reviewManager.requestReviewFlow().await()
            Timber.d("│ ✓ requestReviewFlow() succeeded, ReviewInfo obtained")
            Timber.d("│   reviewInfo class: %s", reviewInfo.javaClass.simpleName)

            Timber.d("│ Calling launchReviewFlow()...")
            Timber.d("│   activity.isFinishing=%s (pre-launch check)", activity.isFinishing)
            reviewManager.launchReviewFlow(activity, reviewInfo).await()
            Timber.d("│ ✓ launchReviewFlow() completed (dialog was shown or quota prevented it)")

            recordPromptAttempt()
            Timber.d("└─── promptIfEligible() done ───")
        } catch (e: Exception) {
            Timber.w(e, "└─── promptIfEligible() FAILED: %s", e.message)
        }
    }

    private suspend fun recordPromptAttempt() {
        context.reviewDataStore.edit { prefs ->
            prefs[KEY_LAST_PROMPT_TIMESTAMP] = System.currentTimeMillis()
            val count = (prefs[KEY_PROMPT_COUNT] ?: 0) + 1
            prefs[KEY_PROMPT_COUNT] = count
            Timber.d("│ Prompt attempt #%d/%d recorded at %s", count, MAX_PROMPT_ATTEMPTS, DATE_FORMAT.format(Date()))
        }
    }

    suspend fun dumpState() {
        val prefs = context.reviewDataStore.data.first()
        val lastTimestamp = prefs[KEY_LAST_PROMPT_TIMESTAMP] ?: 0L
        Timber.d("┌─── Review State Dump ───")
        Timber.d("│ has_posted       = %s", prefs[KEY_HAS_POSTED] ?: false)
        Timber.d("│ review_completed = %s", prefs[KEY_REVIEW_COMPLETED] ?: false)
        Timber.d("│ prompt_count     = %d/%d", prefs[KEY_PROMPT_COUNT] ?: 0, MAX_PROMPT_ATTEMPTS)
        Timber.d("│ last_prompt      = %s", if (lastTimestamp > 0) DATE_FORMAT.format(Date(lastTimestamp)) else "never")
        Timber.d("│ cooldown_ms      = %d (%dh)", PROMPT_COOLDOWN_MS, PROMPT_COOLDOWN_MS / 3600_000)
        if (lastTimestamp > 0) {
            val elapsed = System.currentTimeMillis() - lastTimestamp
            val remaining = PROMPT_COOLDOWN_MS - elapsed
            Timber.d("│ elapsed          = %ds (%dh)", elapsed / 1000, elapsed / 3600_000)
            Timber.d("│ cooldown_remaining = %s", if (remaining > 0) "${remaining / 1000}s (${remaining / 3600_000}h)" else "READY")
        }
        Timber.d("└─────────────────────────")
    }

    companion object {
        private val KEY_HAS_POSTED = booleanPreferencesKey("has_posted")
        private val KEY_LAST_PROMPT_TIMESTAMP = longPreferencesKey("last_review_prompt_timestamp")
        private val KEY_REVIEW_COMPLETED = booleanPreferencesKey("review_completed")
        private val KEY_PROMPT_COUNT = intPreferencesKey("review_prompt_count")
        private const val PROMPT_COOLDOWN_MS = 7L * 24 * 60 * 60 * 1000 // 1 week
        private const val MAX_PROMPT_ATTEMPTS = 12
        private val DATE_FORMAT = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US)
    }
}

fun Context.findActivity(): Activity? {
    Timber.d("findActivity() called on %s", this.javaClass.simpleName)
    var ctx: Context = this
    var depth = 0
    while (ctx is ContextWrapper) {
        if (ctx is Activity) {
            Timber.d("  → found Activity: %s (depth=%d)", ctx.javaClass.simpleName, depth)
            return ctx
        }
        Timber.d("  → unwrapping %s (depth=%d)", ctx.javaClass.simpleName, depth)
        ctx = ctx.baseContext
        depth++
    }
    Timber.w("  → NO Activity found after %d unwraps! Final context: %s", depth, ctx.javaClass.simpleName)
    return null
}

val LocalInAppReviewManager = compositionLocalOf<InAppReviewManager> {
    error("InAppReviewManager not provided")
}
