package net.kibotu.trail.shared.update

import android.content.Context
import androidx.activity.ComponentActivity
import androidx.activity.result.ActivityResultLauncher
import androidx.activity.result.IntentSenderRequest
import androidx.compose.runtime.compositionLocalOf
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.google.android.play.core.appupdate.AppUpdateManagerFactory
import com.google.android.play.core.appupdate.AppUpdateOptions
import com.google.android.play.core.install.model.AppUpdateType
import com.google.android.play.core.install.model.UpdateAvailability
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.tasks.await

private val Context.updateDataStore: DataStore<Preferences>
    by preferencesDataStore(name = "update_prefs")

class InAppUpdateManager(private val context: Context) {

    private val appUpdateManager = AppUpdateManagerFactory.create(context)

    private val lastPromptTimestamp = context.updateDataStore.data.map { prefs ->
        prefs[KEY_LAST_PROMPT_TIMESTAMP] ?: 0L
    }

    private suspend fun shouldPrompt(): Boolean {
        val timestamp = lastPromptTimestamp.first()
        if (timestamp == 0L) return true
        val elapsed = System.currentTimeMillis() - timestamp
        return elapsed >= PROMPT_COOLDOWN_MS
    }

    suspend fun checkAndPromptUpdate(
        activity: ComponentActivity,
        launcher: ActivityResultLauncher<IntentSenderRequest>,
    ) {
        if (!shouldPrompt()) return
        try {
            val appUpdateInfo = appUpdateManager.appUpdateInfo.await()
            if (appUpdateInfo.updateAvailability() == UpdateAvailability.UPDATE_AVAILABLE
                && appUpdateInfo.isUpdateTypeAllowed(AppUpdateType.FLEXIBLE)
            ) {
                recordPromptTimestamp()
                appUpdateManager.startUpdateFlowForResult(
                    appUpdateInfo,
                    launcher,
                    AppUpdateOptions.newBuilder(AppUpdateType.FLEXIBLE).build(),
                )
            }
        } catch (_: Exception) {
            // Never disrupt the user experience over an update check failure
        }
    }

    private suspend fun recordPromptTimestamp() {
        context.updateDataStore.edit { prefs ->
            prefs[KEY_LAST_PROMPT_TIMESTAMP] = System.currentTimeMillis()
        }
    }

    companion object {
        private val KEY_LAST_PROMPT_TIMESTAMP =
            longPreferencesKey("last_update_prompt_timestamp")
        private const val PROMPT_COOLDOWN_MS = 24L * 60 * 60 * 1000
    }
}

val LocalInAppUpdateManager = compositionLocalOf<InAppUpdateManager> {
    error("InAppUpdateManager not provided")
}
