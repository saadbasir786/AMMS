plugins {
    id("com.android.application")
}

android {
    namespace = "af.gov.aop.amms.terminal"
    compileSdk = 35

    val ammsApiBaseUrl = providers.gradleProperty("ammsApiBaseUrl")
        .orElse("http://127.0.0.1:8000")
        .get()

    defaultConfig {
        applicationId = "af.gov.aop.amms.terminal"
        minSdk = 25
        // The deployed SVT3000 SE runs Android 7.1.2 / API 25.
        targetSdk = 25
        versionCode = 1
        versionName = "0.1.0-laravel"

        buildConfigField("String", "AMMS_API_BASE_URL", "\"$ammsApiBaseUrl\"")

        ndk {
            abiFilters.add("armeabi-v7a")
        }
    }

    buildFeatures { buildConfig = true }
}

dependencies {
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.10.2")
    testImplementation("junit:junit:4.13.2")
}
