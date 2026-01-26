package net.kibotu.trail

import android.app.Application
import com.google.firebase.Firebase
import com.google.firebase.crashlytics.crashlytics
import com.google.firebase.initialize
import net.kibotu.trail.di.appModule
import net.kibotu.trail.di.networkModule
import net.kibotu.trail.di.repositoryModule
import net.kibotu.trail.di.viewModelModule
import org.koin.android.ext.koin.androidContext
import org.koin.android.ext.koin.androidLogger
import org.koin.core.context.startKoin
import org.koin.core.logger.Level

class TrailApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        
        // Initialize Firebase
        Firebase.initialize(this)
        Firebase.crashlytics.setCrashlyticsCollectionEnabled(!BuildConfig.DEBUG)
        
        // Initialize Koin
        startKoin {
            androidLogger(if (BuildConfig.DEBUG) Level.DEBUG else Level.ERROR)
            androidContext(this@TrailApplication)
            modules(
                appModule,
                networkModule,
                repositoryModule,
                viewModelModule
            )
        }
    }
}
