package net.kibotu.trail

import android.app.Application
import com.google.firebase.Firebase
import com.google.firebase.crashlytics.crashlytics
import com.google.firebase.initialize
import net.kibotu.trail.di.appModule
import net.kibotu.trail.di.navigationModule
import net.kibotu.trail.di.networkModule
import net.kibotu.trail.di.repositoryModule
import net.kibotu.trail.di.viewModelModule
import org.koin.android.ext.koin.androidContext
import org.koin.android.ext.koin.androidLogger
import org.koin.core.context.startKoin
import org.koin.core.logger.Level
import timber.log.Timber

class TrailApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        
        // Initialize Timber
        if (resources.getBoolean(R.bool.development)) {
            Timber.plant(Timber.DebugTree())
        } else {
            // Plant a tree that logs to Crashlytics in production
            Timber.plant(CrashlyticsTree())
        }
        
        // Initialize Firebase
        Firebase.initialize(this)
        Firebase.crashlytics.isCrashlyticsCollectionEnabled = resources.getBoolean(R.bool.development)
        
        // Initialize Koin
        startKoin {
            androidLogger(if (resources.getBoolean(R.bool.development)) Level.DEBUG else Level.ERROR)
            androidContext(this@TrailApplication)
            modules(
                appModule,
                networkModule,
                repositoryModule,
                viewModelModule,
                navigationModule
            )
        }
        
        Timber.i("TrailApplication initialized")
    }
    
    /**
     * Custom Timber tree that logs errors and warnings to Firebase Crashlytics
     */
    private class CrashlyticsTree : Timber.Tree() {
        override fun log(priority: Int, tag: String?, message: String, t: Throwable?) {
            if (priority == android.util.Log.ERROR || priority == android.util.Log.WARN) {
                Firebase.crashlytics.log("$tag: $message")
                t?.let { Firebase.crashlytics.recordException(it) }
            }
        }
    }
}
