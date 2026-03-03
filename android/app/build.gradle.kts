import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
    alias(libs.plugins.play.services)
    alias(libs.plugins.firebase.crashlytics)
    alias(libs.plugins.baselineprofile)
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
    compileSdk {
        version = release(36) {
            minorApiLevel = 1
        }
    }

    defaultConfig {
        applicationId = "net.kibotu.trail"
        minSdk = 23
        targetSdk = 36
        versionCode = (project.findProperty("versionCode") as String?)?.toIntOrNull() ?: 1
        versionName = (project.findProperty("versionName") as String?) ?: "1.0"

        // Still needed for minSdk 23 to support vector drawable features from API 24+
        // Can be removed when minSdk is upgraded to 24+
        vectorDrawables.useSupportLibrary = true
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"

        ndk {
            abiFilters += listOf("armeabi-v7a", "arm64-v8a", "x86", "x86_64")
        }
    }

    signingConfigs {
        getByName("debug") {
            storeFile =
                file(localProperties.getProperty("DEBUG_KEYSTORE_PATH", "certificates/debug.jks"))
            storePassword = localProperties.getProperty("DEBUG_STORE_PASSWORD", "")
            keyAlias = localProperties.getProperty("DEBUG_KEYSTORE_ALIAS", "debug")
            keyPassword = localProperties.getProperty("DEBUG_KEY_PASSWORD", "")
        }
        create("release") {
            storeFile = file(
                localProperties.getProperty(
                    "RELEASE_KEYSTORE_PATH",
                    "certificates/store.jks"
                )
            )
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
            ndk {
                abiFilters += listOf("armeabi-v7a", "arm64-v8a", "x86", "x86_64")
            }
        }
    }

    // region app bundles

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

    // endregion

    // region dependency info

    dependenciesInfo {
        includeInApk = true
        includeInBundle = true
    }

    // endregion

    // region packing options

    packaging {
        resources {
            // DebugProbesKt.bin is used for java debugging (not needed for android)
            excludes += "DebugProbesKt.bin"
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
            // Note: armeabi, mips, and mips64 are deprecated/removed ABIs - no longer needed in modern Android
            // Keeping only if you have legacy dependencies that still package these
        }
    }

    // endregion

    compileOptions {
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }

    defaultConfig {
        buildConfigField("String", "API_BASE_URL", "\"https://trail.services.kibotu.net/\"")
    }
}

java {
    toolchain {
        languageVersion.set(JavaLanguageVersion.of(17))
    }
}

baselineProfile {
    automaticGenerationDuringBuild = true
    saveInSrc = true
    mergeIntoMain = true
}

// Clean generated baseline profile files to ensure fresh generation
tasks.named<Delete>("clean") {
    delete(
        file("src/main/generated/baselineProfiles/baseline-prof.txt"),
        file("src/main/generated/baselineProfiles/startup-prof.txt")
    )
}

// region version overrides

configurations.configureEach {
    resolutionStrategy {
        capabilitiesResolution.all { selectHighestVersion() }

        // Kotlin stdlib
        force("org.jetbrains.kotlin:kotlin-stdlib:2.3.10")
        force("org.jetbrains.kotlin:kotlin-stdlib-common:2.3.10")
        force("org.jetbrains.kotlin:kotlin-stdlib-jdk7:2.3.10")
        force("org.jetbrains.kotlin:kotlin-stdlib-jdk8:2.3.10")
    }
}

// endregion

dependencies {
    implementation(libs.androidx.profileinstaller)
    "baselineProfile"(project(":baselineprofile"))
    // sugar
    coreLibraryDesugaring(libs.desugar.jdk.libs)

    // firebase
    implementation(platform(libs.firebase.bom))
    implementation(libs.firebase.crashlytics)
    implementation(libs.firebase.analytics)

    // login
    implementation(libs.androidx.credentials)
    implementation(libs.androidx.credentials.auth)
    implementation(libs.googleid)
    implementation(libs.play.services.auth)

    // in-app review
    implementation(libs.play.review)
    implementation(libs.play.review.ktx)

    // in-app update
    implementation(libs.play.update)
    implementation(libs.play.update.ktx)

    // networking with ktor
    implementation(libs.ktor.client.core)
    implementation(libs.ktor.client.android)
    implementation(libs.ktor.client.content.negotiation)
    implementation(libs.ktor.serialization.kotlinx.json)
    implementation(libs.ktor.client.encoding)
    implementation(libs.ktor.client.logging)
    implementation(libs.kotlinx.serialization.json)

    // navigation
    implementation(libs.androidx.navigation.compose)

    // datastore for token storage
    implementation(libs.androidx.datastore.preferences)

    // coil for image loading
    implementation(libs.coil.compose)
    implementation(libs.coil.network)
    implementation(libs.coil.gif)
    implementation(libs.androidx.constraintlayout.compose)

    // media3 for video playback
    implementation(libs.media3.exoplayer)
    implementation(libs.media3.ui)

    // paging
    implementation(libs.paging.runtime)
    implementation(libs.paging.compose)

    // chrome custom tabs
    implementation(libs.androidx.browser)

    // splash screen
    implementation(libs.androidx.core.splashscreen)
    implementation(libs.androidx.splashscreen.compose)

    implementation(libs.androidx.exifinterface)
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.lifecycle.viewmodel.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.graphics)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.compose.material3)
    implementation(libs.androidx.compose.material.icons.extended)
    implementation(libs.font.awesome.compose)
    implementation(libs.haze)
    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(platform(libs.androidx.compose.bom))
    androidTestImplementation(libs.androidx.compose.ui.test.junit4)
    debugImplementation(libs.androidx.compose.ui.tooling)
    debugImplementation(libs.androidx.compose.ui.test.manifest)
}

// endregion
// Version Overrides Task for Kotlin DSL build.gradle.kts
// Usage: Run ./gradlew logVersionOverrides --no-configuration-cache

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

        // Compare versions - prefer stable over pre-release, then by numeric parts
        fun compareVersions(a: String, b: String): Int {
            // Pre-release versions (alpha, beta, rc) are LOWER than stable
            val aIsPreRelease = a.matches(Regex(".*(?i)(alpha|beta|rc|snapshot).*"))
            val bIsPreRelease = b.matches(Regex(".*(?i)(alpha|beta|rc|snapshot).*"))

            // Extract base version (before any suffix)
            val aBase = a.replace(Regex("[-+].*"), "").replace(Regex("[^0-9.]"), "")
            val bBase = b.replace(Regex("[-+].*"), "").replace(Regex("[^0-9.]"), "")

            val aParts = aBase.split(".").map { it.toIntOrNull() ?: 0 }
            val bParts = bBase.split(".").map { it.toIntOrNull() ?: 0 }
            val maxLen = maxOf(aParts.size, bParts.size)

            for (i in 0 until maxLen) {
                val aVal = aParts.getOrNull(i) ?: 0
                val bVal = bParts.getOrNull(i) ?: 0
                if (aVal != bVal) {
                    return aVal.compareTo(bVal)
                }
            }

            // Same base version - stable wins over pre-release
            return when {
                aIsPreRelease && !bIsPreRelease -> -1
                !aIsPreRelease && bIsPreRelease -> 1
                else -> 0
            }
        }

        fun getMaxVersion(versions: Set<String>): String {
            return versions.maxWithOrNull { a, b -> compareVersions(a, b) } ?: ""
        }

        val allConflicts = depVersions.filter { it.value.size > 1 }

        // Separate conflicts into resolved and unresolved
        val (resolvedConflicts, unresolvedConflicts) = allConflicts.entries.partition { (moduleId, versions) ->
            val resolved = resolvedVersions[moduleId] ?: ""
            val maxVersion = getMaxVersion(versions)

            // Kotlin stdlib is always flagged as unresolved for visibility
            if (moduleId.startsWith("org.jetbrains.kotlin:kotlin-stdlib")) {
                return@partition false
            }

            // Resolved if the resolved version matches the max version
            compareVersions(resolved, maxVersion) >= 0
        }

        val resolvedMap = resolvedConflicts.associate { it.key to (resolvedVersions[it.key] ?: "unknown") }.toSortedMap()
        val unresolvedMap = unresolvedConflicts.associate { it.key to (resolvedVersions[it.key] ?: "unknown") }.toSortedMap()

        println("\n${"=".repeat(80)}")
        println("DEPENDENCY VERSION ANALYSIS")
        println("=".repeat(80))
        println()
        println("Total dependencies with multiple versions: ${allConflicts.size}")
        println("  - Resolved correctly: ${resolvedMap.size}")
        println("  - Unresolved (need force): ${unresolvedMap.size}")
        println()

        // Show ALL conflicts first
        if (allConflicts.isNotEmpty()) {
            println("=".repeat(80))
            println("ALL CONFLICTS (${allConflicts.size} total)")
            println("=".repeat(80))
            println()

            allConflicts.toSortedMap().forEach { (moduleId, versions) ->
                val resolvedVersion = resolvedVersions[moduleId] ?: "unknown"
                val maxVersion = getMaxVersion(versions)
                val isResolved = compareVersions(resolvedVersion, maxVersion) >= 0 &&
                        !moduleId.startsWith("org.jetbrains.kotlin:kotlin-stdlib")
                val status = if (isResolved) "✅" else "⚠️"

                println("$status $moduleId")
                println("   requested: ${versions.sorted().joinToString(" | ")}")
                println("   resolved:  $resolvedVersion" + if (!isResolved && resolvedVersion != maxVersion) " (should be $maxVersion)" else "")
                println()
            }
        }

        if (unresolvedMap.isEmpty()) {
            println("=".repeat(80))
            println("✅ All Good! All version conflicts are properly resolved.")
            println("=".repeat(80))
            return@doLast
        }

        println("=".repeat(80))
        println("UNRESOLVED CONFLICTS (${unresolvedMap.size} need attention)")
        println("=".repeat(80))
        println()

        // Generate VERSION_OVERRIDES snippet
        println("=".repeat(80))
        println("VERSION_OVERRIDES (copy & paste):")
        println("=".repeat(80))
        println()
        println("configurations.configureEach {")
        println("    resolutionStrategy {")
        println("        capabilitiesResolution.all { selectHighestVersion() }")

        // Group by category
        fun getCategory(module: String): String {
            return when {
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
        }

        // For output, use appropriate version for each conflict
        // For Kotlin stdlib, all should match the main kotlin-stdlib version
        val kotlinStdlibVersion = resolvedVersions["org.jetbrains.kotlin:kotlin-stdlib"] ?: "2.3.0"
        val toForce = unresolvedMap.mapValues { (moduleId, _) ->
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