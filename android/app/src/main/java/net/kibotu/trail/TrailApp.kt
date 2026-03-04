package net.kibotu.trail

import android.app.Application
import coil3.ImageLoader
import coil3.SingletonImageLoader
import coil3.gif.AnimatedImageDecoder
import coil3.network.okhttp.OkHttpNetworkFetcherFactory
import coil3.svg.SvgDecoder
import timber.log.Timber

class TrailApp : Application(), SingletonImageLoader.Factory {

    override fun onCreate() {
        super.onCreate()
        if (BuildConfig.DEBUG) {
            Timber.plant(Timber.DebugTree())
        }
    }

    override fun newImageLoader(context: coil3.PlatformContext): ImageLoader {
        return ImageLoader.Builder(context)
            .components {
                add(SvgDecoder.Factory())
                add(AnimatedImageDecoder.Factory())
                add(OkHttpNetworkFetcherFactory())
            }
            .build()
    }
}
