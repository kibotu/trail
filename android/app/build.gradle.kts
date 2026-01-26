import java.util.Properties
import kotlin.apply

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("org.jetbrains.kotlin.plugin.compose")
    id("org.jetbrains.kotlin.plugin.serialization")
    id("com.google.gms.google-services")
    id("com.google.firebase.crashlytics")
}

// Load local.properties for Mapbox token and signing configs
val localPropertiesFile = project.file("../local.properties")
val localProperties = Properties().apply {
    if (localPropertiesFile.exists()) {
        load(localPropertiesFile.inputStream())
    }
}

android {
    namespace = "net.kibotu.trail"
    compileSdk = 36
    
    defaultConfig {
        applicationId = "net.kibotu.trail"
        minSdk = 23
        targetSdk = 36
        versionCode = 1
        versionName = "1.0.0"
        
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        vectorDrawables {
            useSupportLibrary = true
        }
    }

    signingConfigs {
        getByName("debug") {
            storeFile = file(localProperties.getProperty("DEBUG_KEYSTORE_PATH", "certificates/debug.jks"))
            storePassword = localProperties.getProperty("DEBUG_STORE_PASSWORD", "")
            keyAlias = localProperties.getProperty("DEBUG_KEYSTORE_ALIAS", "debug")
            keyPassword = localProperties.getProperty("DEBUG_KEY_PASSWORD", "")
        }
        create("release") {
            storeFile = file(localProperties.getProperty("RELEASE_KEYSTORE_PATH", "certificates/release.jks"))
            storePassword = localProperties.getProperty("RELEASE_STORE_PASSWORD", "")
            keyAlias = localProperties.getProperty("RELEASE_KEYSTORE_ALIAS", "release")
            keyPassword = localProperties.getProperty("RELEASE_KEY_PASSWORD", "")
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("release")
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )

            ndk {
                abiFilters += listOf("armeabi-v7a", "arm64-v8a", "x86", "x86_64")
            }
        }
        debug {
            signingConfig = signingConfigs.getByName("debug")
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            // Include all ABIs for emulator and device testing
            ndk {
                abiFilters += listOf("armeabi-v7a", "arm64-v8a", "x86", "x86_64")
            }
        }
    }
    
    buildFeatures {
        compose = true
    }

    bundle {
        density {
            enableSplit = true
        }
        abi {
            enableSplit = true
        }
        language {
            enableSplit = false
        }
    }

    compileOptions {
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    packaging {
        resources {
            // DebugProbesKt.bin is used for java debugging (not needed for android)
            // Hint: https://github.com/Kotlin/kotlinx.coroutines/issues/2274
            excludes += "DebugProbesKt.bin"
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
            // https://stackoverflow.com/a/61893957/1006741
            excludes -= "/META-INF/*.kotlin_module"

            excludes += "META-INF/maven/com.google.guava/guava/pom.properties"
            excludes += "META-INF/maven/com.google.guava/guava/pom.xml"

            // Modern phones support arm64-v8a, armeabi-v7a, armeabi
            // x86 and x86_64 is used by the emulator and some chromebooks
            // Exclude optimized ARCHs to save apk size
            excludes += "lib/armeabi/**"   // older, slow arm format
            excludes += "lib/mips/**"      // modern phones don't support this
            excludes += "lib/mips64/**"    // modern phones don't support this

            // avoid this error: More than one file was found with OS independent path 'README.txt'
            excludes += "README.txt"

            // Exclude test support native libraries from release builds
            excludes += "lib/**/libandroid-tests-support-code.so"
        }
    }
}

java {
    toolchain {
        languageVersion.set(JavaLanguageVersion.of(17))
    }
}

dependencies {
    implementation("net.kibotu:AndroidResourceExtensions:2.0.3")
    implementation("net.kibotu:ApplicationProvider:2.1.5")

    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.5")

    // Compose BOM
    implementation(platform("androidx.compose:compose-bom:2026.01.00"))
    implementation("androidx.compose.ui:ui")
    implementation("androidx.compose.material3:material3")
    implementation("androidx.compose.ui:ui-tooling-preview")
    debugImplementation("androidx.compose.ui:ui-tooling")
    debugImplementation("androidx.compose.ui:ui-test-manifest")
    
    // Credential Manager for Google Sign-In
    implementation("androidx.credentials:credentials:1.5.0")
    implementation("androidx.credentials:credentials-play-services-auth:1.5.0")
    implementation("com.google.android.libraries.identity.googleid:googleid:1.2.0")
    
    // Ktor Client
    implementation("io.ktor:ktor-client-core:3.4.0")
    implementation("io.ktor:ktor-client-android:3.4.0")
    implementation("io.ktor:ktor-client-content-negotiation:3.4.0")
    implementation("io.ktor:ktor-serialization-kotlinx-json:3.4.0")
    implementation("io.ktor:ktor-client-logging:3.4.0")
    implementation("io.ktor:ktor-client-auth:3.4.0")
    
    // Kotlin Serialization
    implementation("org.jetbrains.kotlinx:kotlinx-serialization-json:1.10.0")
    
    // Koin
    implementation("io.insert-koin:koin-androidx-compose:4.1.1")
    implementation("io.insert-koin:koin-androidx-compose-navigation:4.1.1")
    
    // Navigation 3
    implementation("androidx.navigation3:navigation3-runtime:1.0.0")
    implementation("androidx.navigation3:navigation3-ui:1.0.0")
    
    // Firebase
    implementation(platform("com.google.firebase:firebase-bom:34.8.0"))
    implementation("com.google.firebase:firebase-crashlytics")
    implementation("com.google.firebase:firebase-analytics")
    
    // DataStore
    implementation("androidx.datastore:datastore-preferences:1.2.0")
    
    // Lifecycle
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.10.0")
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.10.0")
    
    // Activity Compose
    implementation("androidx.activity:activity-compose:1.12.2")
    
    // Coil for image loading
    implementation("io.coil-kt:coil-compose:2.7.0")
    
    // Testing
    testImplementation("junit:junit:4.13.2")
    testImplementation("io.ktor:ktor-client-mock:3.4.0")
    testImplementation("io.insert-koin:koin-test:4.1.1")
    testImplementation("io.insert-koin:koin-test-junit4:4.1.1")
    testImplementation("org.jetbrains.kotlinx:kotlinx-coroutines-test:1.10.2")
    androidTestImplementation("androidx.test.ext:junit:1.3.0")
    androidTestImplementation("androidx.test.espresso:espresso-core:3.7.0")
    androidTestImplementation("androidx.compose.ui:ui-test-junit4")
}
