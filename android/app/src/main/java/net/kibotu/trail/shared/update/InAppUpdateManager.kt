package net.kibotu.trail.shared.update

import android.content.Context
import timber.log.Timber
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
import com.google.android.play.core.install.InstallStateUpdatedListener
import com.google.android.play.core.install.model.AppUpdateType
import com.google.android.play.core.install.model.InstallStatus
import com.google.android.play.core.install.model.UpdateAvailability
import com.google.android.play.core.ktx.installStatus
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.tasks.await

private val Context.updateDataStore: DataStore<Preferences>
    by preferencesDataStore(name = "update_prefs")

class InAppUpdateManager(private val context: Context) {

    private val appUpdateManager = AppUpdateManagerFactory.create(context)

    private val installStateListener = InstallStateUpdatedListener { state ->
        when (state.installStatus) {
            InstallStatus.DOWNLOADED -> {
                Timber.d("Update downloaded, completing update")
                appUpdateManager.completeUpdate()
            }
            InstallStatus.FAILED -> Timber.w("Update install failed")
            InstallStatus.DOWNLOADING -> Timber.d("Update downloading...")
            else -> Timber.d("Install status: %s", state.installStatus)
        }
    }

    private suspend fun shouldPrompt(): Boolean {
        val timestamp = context.updateDataStore.data.first()[KEY_LAST_PROMPT_TIMESTAMP] ?: 0L
        if (timestamp == 0L) {
            Timber.d("shouldPrompt: true (first time)")
            return true
        }
        val elapsed = System.currentTimeMillis() - timestamp
        val eligible = elapsed >= PROMPT_COOLDOWN_MS
        Timber.d("shouldPrompt: %s (elapsed %ds, cooldown %ds)", eligible, elapsed / 1000, PROMPT_COOLDOWN_MS / 1000)
        return eligible
    }

    suspend fun checkAndPromptUpdate(
        activity: ComponentActivity,
        launcher: ActivityResultLauncher<IntentSenderRequest>,
    ) {
        if (!shouldPrompt()) return
        try {
            val appUpdateInfo = appUpdateManager.appUpdateInfo.await()
            Timber.d("Update availability: %s, flexible allowed: %s",
                appUpdateInfo.updateAvailability(),
                appUpdateInfo.isUpdateTypeAllowed(AppUpdateType.FLEXIBLE))

            if (appUpdateInfo.updateAvailability() == UpdateAvailability.UPDATE_AVAILABLE
                && appUpdateInfo.isUpdateTypeAllowed(AppUpdateType.FLEXIBLE)
            ) {
                appUpdateManager.registerListener(installStateListener)
                appUpdateManager.startUpdateFlowForResult(
                    appUpdateInfo,
                    launcher,
                    AppUpdateOptions.newBuilder(AppUpdateType.FLEXIBLE).build(),
                )
                recordPromptTimestamp()
                Timber.d("Update flow started")
            } else if (appUpdateInfo.updateAvailability() == UpdateAvailability.DEVELOPER_TRIGGERED_UPDATE_IN_PROGRESS) {
                Timber.d("Resuming in-progress update")
                appUpdateManager.registerListener(installStateListener)
                appUpdateManager.startUpdateFlowForResult(
                    appUpdateInfo,
                    launcher,
                    AppUpdateOptions.newBuilder(AppUpdateType.FLEXIBLE).build(),
                )
            }
        } catch (e: Exception) {
            Timber.w(e, "Update check failed: %s", e.message)
        }
    }

    fun unregisterListener() {
        appUpdateManager.unregisterListener(installStateListener)
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
