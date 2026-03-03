package net.kibotu.trail

import android.app.Application
import timber.log.Timber

class TrailApp : Application() {

    override fun onCreate() {
        super.onCreate()
        if (BuildConfig.DEBUG) {
            Timber.plant(Timber.DebugTree())
        }
    }
}
