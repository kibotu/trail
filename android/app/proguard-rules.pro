# Firebase Crashlytics
-keepattributes SourceFile,LineNumberTable
-keep public class * extends java.lang.Exception
-keep class com.google.firebase.crashlytics.** { *; }
-dontwarn com.google.firebase.crashlytics.**

# Google Play Core (In-App Review, In-App Update)
-keep class com.google.android.play.core.** { *; }
-dontwarn com.google.android.play.core.**
