plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "com.amms.terminal"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.amms.terminal"
        minSdk = 26
        targetSdk = 35
        versionCode = 1
        versionName = "0.1.0-flow10a"
        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    buildTypes {
        debug {
            buildConfigField("boolean", "FLOW10A_FAKE_ADAPTER", "true")
        }
        release {
            isMinifyEnabled = false
            buildConfigField("boolean", "FLOW10A_FAKE_ADAPTER", "false")
        }
    }

    buildFeatures { buildConfig = true }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_21
        targetCompatibility = JavaVersion.VERSION_21
    }
    kotlinOptions { jvmTarget = "21" }
}

dependencies {
    implementation("androidx.core:core-ktx:1.15.0")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("com.google.android.material:material:1.12.0")
    testImplementation("junit:junit:4.13.2")
}
