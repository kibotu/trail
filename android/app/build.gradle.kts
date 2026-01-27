import java.util.Properties
import kotlin.apply

plugins {
    id("com.android.application")
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

    // https://mvnrepository.com/artifact/com.squareup.leakcanary/plumber-android
    implementation("com.squareup.leakcanary:plumber-android:2.14")
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
    // Using latest versions with Android 16 edge-to-edge fixes
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
    // https://central.sonatype.com/artifact/io.insert-koin/koin-compose/versions
    implementation("io.insert-koin:koin-androidx-compose:4.2.0-beta4")
    // https://central.sonatype.com/artifact/io.insert-koin/koin-compose-navigation3-android/versions
    implementation("io.insert-koin:koin-compose-navigation3-android:4.2.0-beta4")
    
    // Navigation 3
    implementation("androidx.navigation3:navigation3-runtime:1.0.0")
    implementation("androidx.navigation3:navigation3-ui:1.0.0")
    implementation("androidx.lifecycle:lifecycle-viewmodel-navigation3:2.10.0")
    implementation("androidx.compose.material3.adaptive:adaptive-navigation3:1.3.0-alpha06")
    
    // Kotlin Serialization for Navigation 3
    implementation("org.jetbrains.kotlinx:kotlinx-serialization-core:1.10.0")
    
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
    
    // Timber for logging
    implementation("com.jakewharton.timber:timber:5.0.1")

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


// Task to analyze dependencies and output necessary version overrides
// Similar to version-overrides/generate.py - finds actual duplicate versions
tasks.register("logVersionOverrides") {
    group = "help"
    description = "Analyzes dependencies for actual version conflicts (same dep with multiple versions)"

    doLast {
        // Track all versions seen for each dependency
        val depVersions = mutableMapOf<String, MutableSet<String>>()
        val resolvedVersions = mutableMapOf<String, String>()

        // Analyze releaseRuntimeClasspath configuration
        val configName = "releaseRuntimeClasspath"
        val config = configurations.findByName(configName)

        if (config != null && config.isCanBeResolved) {
            config.incoming.resolutionResult.allDependencies.forEach { dependency ->
                if (dependency is org.gradle.api.artifacts.result.ResolvedDependencyResult) {
                    val requested = dependency.requested
                    val selected = dependency.selected.moduleVersion

                    if (requested is org.gradle.api.artifacts.component.ModuleComponentSelector && selected != null) {
                        val requestedVersion = requested.version
                        val selectedVersion = selected.version
                        val moduleId = "${requested.group}:${requested.module}"

                        // Track ALL versions requested for this dependency
                        if (requestedVersion.isNotEmpty()) {
                            depVersions.getOrPut(moduleId) { mutableSetOf() }.add(requestedVersion)
                        }
                        resolvedVersions[moduleId] = selectedVersion
                    }
                }
            }
        }

        // Find dependencies with multiple different versions (actual conflicts)
        // Compare versions - prefer stable over pre-release, then by numeric parts
        fun compareVersions(a: String, b: String): Int {
            // Pre-release versions (alpha, beta, rc) are LOWER than stable
            val aIsPreRelease = a.contains(Regex("(?i)(alpha|beta|rc|snapshot)"))
            val bIsPreRelease = b.contains(Regex("(?i)(alpha|beta|rc|snapshot)"))

            // Extract base version (before any suffix)
            val aBase = a.replace(Regex("[-+].*"), "").replace(Regex("[^0-9.]"), "")
            val bBase = b.replace(Regex("[-+].*"), "").replace(Regex("[^0-9.]"), "")

            val aParts = aBase.split(".").map { it.toIntOrNull() ?: 0 }
            val bParts = bBase.split(".").map { it.toIntOrNull() ?: 0 }
            val maxLen = maxOf(aParts.size, bParts.size)

            for (i in 0 until maxLen) {
                val aVal = aParts.getOrElse(i) { 0 }
                val bVal = bParts.getOrElse(i) { 0 }
                if (aVal != bVal) return aVal.compareTo(bVal)
            }

            // Same base version - stable wins over pre-release
            if (aIsPreRelease && !bIsPreRelease) return -1
            if (!aIsPreRelease && bIsPreRelease) return 1
            return 0
        }

        fun getMaxVersion(versions: Set<String>): String {
            return versions.maxWithOrNull { a, b -> compareVersions(a, b) } ?: versions.first()
        }

        val allConflicts = depVersions.filter { it.value.size > 1 }

        // A conflict is UNRESOLVED only if:
        // 1. Resolved version is LOWER than max requested version
        // 2. For Kotlin stdlib, we always flag to ensure consistency
        val unresolvedConflicts = allConflicts.filter { (moduleId, versions) ->
            val resolved = resolvedVersions[moduleId] ?: ""
            val maxVersion = getMaxVersion(versions)

            // Always flag Kotlin stdlib for visibility (they need to match Kotlin plugin version)
            if (moduleId.startsWith("org.jetbrains.kotlin:kotlin-stdlib")) {
                return@filter true
            }

            // Only flag if resolved is LOWER than max (force might set it higher, that's fine)
            compareVersions(resolved, maxVersion) < 0
        }.mapValues { (moduleId, _) -> resolvedVersions[moduleId] ?: "unknown" }.toSortedMap()

        println("\n" + "=".repeat(80))
        println("DEPENDENCY VERSION ANALYSIS")
        println("=".repeat(80))
        println()
        println("Total dependencies with multiple versions: ${allConflicts.size}")
        println("Unresolved (need force): ${unresolvedConflicts.size}")
        println()

        if (unresolvedConflicts.isEmpty()) {
            println("✅ All Good! All version conflicts are properly resolved.")
            println()
            println("=".repeat(80))
            return@doLast
        }

        println("Unresolved conflicts:")
        println()

        // Show unresolved conflicts with their versions
        unresolvedConflicts.forEach { (moduleId, resolvedVersion) ->
            val versions = depVersions[moduleId]?.sorted()?.joinToString(" | ") ?: ""
            val maxVersion = getMaxVersion(depVersions[moduleId] ?: emptySet())
            println("$moduleId")
            println("  versions: $versions")
            println("  resolved: $resolvedVersion" + if (resolvedVersion != maxVersion) " (should be $maxVersion)" else "")
            println()
        }

        // Generate VERSION_OVERRIDES snippet
        println("=".repeat(80))
        println("VERSION_OVERRIDES (copy & paste):")
        println("=".repeat(80))
        println()
        println("configurations.configureEach {")
        println("    resolutionStrategy {")
        println("        capabilitiesResolution.all { selectHighestVersion() }")

        // Group by category
        fun getCategory(module: String) = when {
            module.startsWith("org.jetbrains.kotlin:kotlin-stdlib") -> "Kotlin stdlib"
            module.startsWith("org.jetbrains.kotlin:") -> "Kotlin"
            module.startsWith("org.jetbrains.kotlinx:kotlinx-coroutines") -> "Kotlinx Coroutines"
            module.startsWith("org.jetbrains.kotlinx:kotlinx-serialization") -> "Kotlinx Serialization"
            module.startsWith("org.jetbrains.kotlinx:kotlinx-io") -> "Kotlinx IO"
            module.startsWith("org.jetbrains.kotlinx:") -> "Kotlinx"
            module.startsWith("org.jetbrains:") -> "JetBrains"
            module.startsWith("androidx.compose") -> "Compose"
            module.startsWith("androidx.lifecycle") -> "Lifecycle"
            module.startsWith("androidx.") -> "AndroidX"
            module.startsWith("com.google.firebase") -> "Firebase"
            module.startsWith("com.google.android.gms") -> "Play Services"
            module.startsWith("com.squareup.okhttp") -> "OkHttp"
            module.startsWith("com.squareup.okio") -> "Okio"
            else -> "Other"
        }

        // For output, use appropriate version for each conflict
        // For Kotlin stdlib, all should match the main kotlin-stdlib version
        val kotlinStdlibVersion = resolvedVersions["org.jetbrains.kotlin:kotlin-stdlib"] ?: "2.3.0"
        val toForce = unresolvedConflicts.mapValues { (moduleId, _) ->
            if (moduleId.startsWith("org.jetbrains.kotlin:kotlin-stdlib")) {
                kotlinStdlibVersion
            } else {
                getMaxVersion(depVersions[moduleId] ?: emptySet())
            }
        }

        val grouped = toForce.entries.groupBy { getCategory(it.key) }

        grouped.forEach { (category, entries) ->
            println()
            println("        // $category")
            entries.forEach { (module, version) ->
                println("        force(\"$module:$version\")")
            }
        }

        println("    }")
        println("}")
        println()
        println("=".repeat(80))
    }
}

configurations.configureEach {
    resolutionStrategy {
        capabilitiesResolution.all { selectHighestVersion() }

        // Kotlin stdlib
        force("org.jetbrains.kotlin:kotlin-stdlib:2.3.0")
        force("org.jetbrains.kotlin:kotlin-stdlib-jdk7:2.3.0")
        force("org.jetbrains.kotlin:kotlin-stdlib-jdk8:2.3.0")
    }
}
