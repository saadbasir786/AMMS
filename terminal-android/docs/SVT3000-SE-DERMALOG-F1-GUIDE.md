# DERMALOG F1 on SVT3000 SE
## Technical Integration and Development Guide

**Document status:** Operational reference based on reverse engineering, real-device testing, and comparison with the Tutorial and official diagnostic applications

**Last verified:** 2026-08-10

**Target device:** DERMALOG SVT3000 SE

**Android:** 7.1.2 / API 25

**ABI:** armeabi-v7a

This document defines the technical contract for future applications that use the integrated DERMALOG F1 fingerprint sensor on this device. Any change to the SDK, native libraries, firmware, power path, or lifecycle must be revalidated on the real target.

## 1. Executive result

The following complete chain has been successfully executed on the real SVT3000 SE:

~~~
new EmbeddedBiometricsSDK(context)
        ↓
initialize()
        ↓
requestUSBPermissions(...)
        ↓
permissionsResult(0)
        ↓
isInitialized() == true
        ↓
queryDevices()
        ↓
open(device)
        ↓
scannerGetImageSize()
        ↓
scannerAutoCapture(0L, buffer, captureInfo)
        ↓
BIH validation + image preview
        ↓
templateExtract(..., 0)
        ↓
templateVerify(...)
~~~

Observed results:

| Item | Observed result |
|---|---|
| SDK version | 2.8.1.1818 |
| initialize() | 0 |
| USB permission | 0 - Granted |
| isInitialized() | true |
| Devices found | 1 |
| Device ID | 0 |
| Device type | F1 / ZF1 (1) |
| Example serial | F1 24089880 |
| Image size | 320 x 480 |
| BIH buffer | 154664 bytes |
| BIH validation | VALID |
| Template format | DERMALOG (0) |
| Same-finger verification | approximately 60.504 |
| Different-finger verification | approximately 2.18 |

The current diagnostic threshold is 30.0:

- score below 30: NO MATCH
- score equal to or above 30: MATCH

This threshold is provisional for diagnostic use. It is not a certified security threshold.

## 2. Hardware and connection model

The fingerprint module in the SVT3000 SE is not attached through a separate external USB cable. It is integrated into the device. Nevertheless, Android and the DERMALOG SDK manage it as an internal USB device.

Observed hardware identity:

~~~
USB VID:PID = 1FBA:0013
Device type = ZF1 / F1
Device ID   = 0
Serial      = F1 24089880
~~~

The sensor power rail is controlled through the device HAL:

~~~
DeviceManager.getDevice(context)
    -> getPowerManager()
    -> open()
    -> PowerManager.PowerType.USB_FINGERPRINT_SCANNER
    -> power(type, true/false)
~~~

Older vendor images may use this fallback:

~~~
PosUtil.setFingerPrintPower(1) // on
PosUtil.setFingerPrintPower(0) // off
~~~

### ADB behavior

Switching the fingerprint power rail may reset the USB host and temporarily disconnect ADB. The same behavior was observed in the Tutorial application. It is not, by itself, evidence of an application or scanner failure.

After power-on, wait for the F1 device to be published again. The current wrapper waits for the target VID/PID for approximately 8 seconds during USB permission preparation.

## 3. Required artifacts

Proprietary files must come from the installation or system image of the target device family. The expected project location is:

~~~
app/libs/
~~~

Required armeabi-v7a native libraries:

~~~
libdermalogembeddedbiometrics.so
libZF1ScanAPI.so
libusb-1.0.so
libusb-android-1.0.so
libdermalogfingercode3.so
libdermalogcalibratesdk.so
libgnustl_shared.so
libjnidispatch.so
~~~

The SDK reference APK/Dex is also required. The expected artifact list is maintained in app/libs/README.md.

At runtime, the wrapper uses the following classloader preference:

1. com.dermalog.android.diag, the official diagnostic application installed on the device.
2. com.dermalog.hardware, the Tutorial application and hardware helpers.
3. The SDK APK bundled in assets, if the vendor applications are unavailable.

The runtime version observed during testing was 2.8.1.1818. Do not assume that an artifact filename and the runtime version are the same without checking getVersion() on the target.

### Runtime dependency matrix

| Device state | Runtime selected by the wrapper | Deployment implication |
|---|---|---|
| `com.dermalog.android.diag` installed | Its APK, native-library directory, and classloader | Preferred and validated path on the test device |
| Diagnostic absent, `com.dermalog.hardware` installed | Tutorial APK, native-library directory, and classloader | Compatibility fallback for the hardware helper classes |
| Neither vendor package installed | Bundled `assets/dermalog-sdk.apk` copied to code cache and loaded with `DexClassLoader` | The app must include the reference APK and the `armeabi-v7a` native libraries; the integrated-power path must be retested on that image |

The first two rows are the validated target configurations. The fully standalone fallback exists for development compatibility, but it must not be treated as a proven replacement for the vendor applications without a fresh hardware test.

### Do not manually preload native libraries

The official application uses the SDK's own native loader. Additional System.loadLibrary calls or loading the same .so files multiple times may crash the C++ runtime before initialize() is reached.

The wrapper sets these vendor properties before SDK construction:

~~~
DERMALOG_GLOBAL_SYNC_DIR
DERMALOG_NATIVE_LIBRARY_DIR
~~~

### Application identity, manifest, and signing

Current Android application settings:

| Setting | Value |
|---|---|
| Application ID | `af.gov.aop.amms.terminal` |
| Namespace | `af.gov.aop.amms.terminal` |
| Minimum SDK | 25 |
| Target SDK | 25 |
| Supported ABI | `armeabi-v7a` only |
| USB host feature | Declared with `android:required="false"` |

The manifest declares `android.hardware.usb.host` and these compatibility permission names:

~~~text
android.permission.USB_PERMISSION
com.dermalog.android.usb.USBManager.USB_PERMISSION
com.dermalog.hardware.generic.USB_PERMISSION
com.android.example.USB_PERMISSION
~~~

It also registers `android.hardware.usb.action.USB_DEVICE_ATTACHED` on `MainActivity`. These declarations do not replace the vendor runtime permission flow: the app must still call `requestUSBPermissions(...)` after F1 power-on and treat callback result `0` as granted. All non-zero results are treated by the current app as denied/error.

Debug builds use a project-root `debug.keystore` with the standard Android debug alias and password. That file is intentionally ignored by Git; a clean environment must provide or generate it. Release builds use the following environment variables:

~~~text
KEYSTORE_PATH   optional; defaults to <project>/my-upload-key.jks
STORE_PASSWORD  required release keystore password
KEY_PASSWORD    required release key password
keyAlias        upload
~~~

Never commit a release keystore, passwords, or local device configuration.

## 4. SDK contract and wrapper

Because SDK versions expose different methods and because some methods in the official SDK are protected, the project uses a narrow reflection-based boundary:

~~~
app/src/main/java/com/dermalog/android/embeddedbiometrics/EmbeddedBiometricsSDK.java
app/src/main/java/com/dermalog/android/embeddedbiometrics/EmbeddedBiometricsHandle.java
~~~

Reflection is intentionally limited to the vendor boundary. Application code should use the wrapper rather than vendor classes directly.

| API | Purpose | Important detail |
|---|---|---|
| EmbeddedBiometricsSDK(context) | Create SDK instance | May enter native initialization; never call on main |
| initialize() | Initialize SDK | Successful result on this device is 0 |
| isInitialized() | Check SDK state | Check before query/open |
| getVersion() | Read runtime SDK version | 2.8.1.1818 was observed |
| requestUSBPermissions(callback) | Request vendor/USB permission | Callback is asynchronous |
| queryDevicesAndroid() | Query Android device entries | Used by the official SDK |
| queryDevices() | Generic query fallback | Kept for older SDKs |
| openAndroid(...) | Open the real Android device entry | Protected in the official SDK |
| open(DEBDeviceEntry) | Generic open fallback | Used for other SDK variants |
| uninitialize() | SDK-level shutdown | Run on IO |
| scannerGetImageSize() | Read image and buffer size | Returns 320x480 and 154664 |
| scannerAutoCapture(...) | Touch-aware capture | Primary capture API for this target |
| scannerAutoCaptureCancel() | Cancel auto-capture | Available for a future Cancel action |
| scannerSetLed(side, color) | Set scanner LED | Only the left side is supported |
| templateExtract(image, format) | Extract vendor template | Format is 0 |
| templateVerify(...) | Compare templates | Both templates must use format 0 |

## 5. Correct lifecycle

### Step 1: Construct and initialize

~~~kotlin
val sdk = EmbeddedBiometricsSDK(applicationContext)
val result = sdk.initialize()
check(result == 0)
~~~

The constructor, initialize call, and power-on must run on Dispatchers.IO. The USB permission callback must verify isInitialized again after it returns.

### Step 2: Request permission

~~~kotlin
sdk.requestUSBPermissions(object : IUSBPermission {
    override fun permissionsResult(result: Int) {
        // result == 0 means Granted
    }
})
~~~

Do not request permission before the power rail is enabled or before the F1 device has been published by the USB subsystem.

### Step 3: Query the device

~~~kotlin
val devices = sdk.queryDevices()
val device = devices.firstOrNull()
~~~

The official SDK exposes getId(), while some older versions expose getID(). The wrapper supports both. Without this fallback the application produced:

~~~
NoSuchMethodException - getID []
~~~

### Step 4: Open the scanner

~~~kotlin
val handle = sdk.open(device)
val size = handle.scannerGetImageSize()
~~~

Constructing a new vendor DEBDeviceEntry directly caused:

~~~
NoSuchMethodException - <init> [int, int, class java.lang.String]
~~~

The reliable path for the official SDK is to retain the real Android device object returned by queryDevicesAndroid() and invoke protected openAndroid(...) through reflection. The generic constructor path remains only as a compatibility fallback.

## 6. Touch-aware capture

The Tutorial uses:

~~~
scannerAutoCapture(long, byte[], DEBCaptureInfo)
scannerAutoCaptureCancel()
~~~

The signatures extracted from its DEX were:

~~~
scannerAutoCapture(J, [B, Lcom/dermalog/android/embeddedbiometrics/DEBCaptureInfo;) -> V
scannerAutoCaptureCancel() -> V
~~~

A timeout of 0L is used to wait for touch detection. The call must run on a worker thread and may block until detection, timeout, or cancellation.

### Cancellation and timeout contract

The current diagnostic UI starts `scannerAutoCapture(0L, ...)`; therefore its active capture has no application-enforced timeout and continues until the SDK returns or a cancellation is issued. `CAPTURE_WAIT_TIMEOUT_MS` exists as a legacy constant but is not applied to the current auto-capture call.

`scannerAutoCaptureCancel()` is exposed by the wrapper but is not yet connected to a Cancel button. A future production UI should provide Cancel, call that method from IO, wait for the capture coroutine to finish, and only then allow close or reinitialize. Close remains disabled while a capture operation is active.

### API to avoid for the primary flow

scannerCapture can return immediately with a frame. A manual loop around it may capture an image even when no finger has been placed. In one SDK variant, treating scannerCapture as returning an int when the actual method returned void caused a NullPointerException.

Use this pattern:

~~~kotlin
val imageSize = handle.scannerGetImageSize()
val rawBih = ByteArray(imageSize.sizeBIH)
val captureInfo = DEBCaptureInfo()

// Must run on Dispatchers.IO.
handle.scannerAutoCapture(0L, rawBih, captureInfo)

if (captureInfo.isDetect()) {
    // validate, preview, save and extract template
} else {
    // Do not save a capture.
}
~~~

### LED sequence

Expected Tutorial-like behavior:

~~~
Before touch: GREEN blinking
After detection: short GREEN solid state
After capture: short RED state
End: OFF
~~~

Constants:

~~~
SIDE_LEFT  = 0
SIDE_RIGHT = 1
LED_OFF    = 0
LED_RED    = 1
LED_GREEN  = 2
~~~

Real-device testing showed that LEFT RED and LEFT GREEN work, while RIGHT RED and RIGHT GREEN produce InvocationTargetException. Use SIDE_LEFT only. Treat “ALL OFF” as a UI label, not as a hardware side; switch LEFT/OFF explicitly.

## 7. BIH format and image preview

The F1 output is an 8-bit DIB/BIH image, not a normal JPEG or PNG:

~~~
Width              = 320
Height             = 480
Bits per pixel     = 8
Planes             = 1
Compression        = 0
Palette            = 256 x 4 bytes
BITMAPINFOHEADER   = 40 bytes
Pixel offset       = 40 + 1024 = 1064
Row stride         = 320 bytes
Total size         = 1064 + (320 x 480) = 154664 bytes
~~~

Processing order:

1. Obtain the buffer size from scannerGetImageSize(); do not hard-code it.
2. Read the header as little-endian.
3. Compare the dimensions with the scanner specification.
4. Read the palette.
5. Handle bottom-up and top-down DIB row ordering.
6. Create a Bitmap only after validation.

Relevant project files:

~~~
app/src/main/java/com/example/ui/BihValidator.kt
app/src/main/java/com/example/ui/BihImageDecoder.kt
~~~

The validator checks the header, dimensions, planes, bit depth, compression, palette, pixel buffer, mean, standard deviation, and dynamic range.

Observed valid samples:

~~~
Mean: 214.1, StdDev: 74.6, Range: 255
Mean: 209.3, StdDev: 77.4, Range: 255
~~~

These are diagnostic examples, not production quality thresholds.

## 8. File storage and metadata

Files are saved in app-specific external storage:

~~~
/storage/emulated/0/Android/data/<package>/files/Documents/
~~~

Filename patterns:

~~~
f1_capture_<serial>_<yyyyMMdd_HHmmss>.bih.raw
f1_capture_<serial>_<yyyyMMdd_HHmmss>_metadata.json
f1_capture_<serial>_<yyyyMMdd_HHmmss>.dermalog.template
~~~

Recommended metadata:

~~~json
{
  "deviceSerial": "F1 24089880",
  "imageWidth": 320,
  "imageHeight": 480,
  "bihSize": 154664,
  "captureDurationMs": 701,
  "touchStatus": false,
  "detectStatus": true,
  "timeoutStatus": false,
  "cancelledStatus": false,
  "fakeScore": -1,
  "validationStatus": "VALID",
  "templateFormat": 0
}
~~~

A fakeScore of -1 means that no usable anti-spoof value was returned by this device/API. It must not be interpreted as an anti-spoof result.

### Biometric data lifetime and privacy contract

The verification reference is held only in process memory (`referenceTemplate`) and is lost when the process or ViewModel is recreated. In contrast, raw BIH, metadata JSON, and extracted template files are persisted under the app-specific Documents directory.

The current diagnostic app has no automatic retention, encryption, export authorization, or secure-delete policy. A production application must define those controls before using this storage for identity decisions. In particular, do not write raw BIH or templates to shared/public storage, logs, crash reports, or source control.

## 9. Template extraction and verification

Vendor format:

~~~
templateFormat = 0
Display name     = DERMALOG
~~~

Extraction:

~~~java
byte[] template = handle.templateExtract(
        rawBih,
        EmbeddedBiometricsHandle.TEMPLATE_FORMAT_DERMALOG
);
~~~

Only extract a template when:

- captureInfo.isDetect() is true;
- BIH validation is valid;
- the buffer is complete.

Verification:

~~~java
double score = handle.templateVerify(
        0, referenceTemplate,
        0, probeTemplate
);
~~~

Both templates should use the same format and, preferably, the same handle and SDK version.

Current diagnostic result:

~~~
score < 30.0  => NO MATCH
score >= 30.0 => MATCH
~~~

### required format is not supported

This error occurred when the extracted probe was valid but the stored reference was incompatible with the expected format or SDK version.

Prevent it by:

1. Clearing the reference after an SDK or session change.
2. Creating both reference and probe with format 0.
3. Rejecting templates from an unknown SDK/device.
4. Displaying a verification exception as VERIFY ERROR, not NO MATCH.
5. Creating the reference from a clean capture of the same finger.

The observed same-finger score was approximately 60.504 and the different-finger score approximately 2.18. This is useful diagnostic evidence, but a production threshold must be calibrated with a real dataset.

## 10. Close lifecycle and the native crash

After capture, the following path caused a crash or freeze on the SVT3000:

~~~
capture
    -> scannerSetLed(OFF)
    -> handle.dispose()
    -> vendor DEBDestroyHandle
~~~

The real native log contained:

~~~
JNI DETECTED ERROR IN APPLICATION
DeleteGlobalRef
DEBDestroyHandle
ClEmbeddedBiometricsHandle::~ClEmbeddedBiometricsHandle()
~~~

Calling dispose on a worker thread caused a JNI abort. Calling it on the main thread caused an ANR because the native destructor blocks.

On the tested firmware, dispose is therefore deliberately skipped. The current close sequence is:

~~~
Lock operation
    -> best-effort LEFT/OFF LED
    -> do not call handle.dispose()
    -> sdk.uninitialize() on Dispatchers.IO
    -> disableFingerprintPower() on Dispatchers.IO
    -> clear UI state
~~~

The same rule is applied in onCleared(). This is a target-image-specific workaround; another model or firmware must be tested independently.

Capture and close are protected by a shared Mutex, and _isProcessing disables competing actions. No long native operation is allowed on the main thread.

## 11. Threading and UI rules

The following calls may block or enter native code and must run on Dispatchers.IO:

~~~
EmbeddedBiometricsSDK(context)
initialize()
requestUSBPermissions()
queryDevices()
open()
scannerGetImageSize()
scannerAutoCapture()
templateExtract()
templateVerify()
uninitialize()
disableFingerprintPower()
~~~

The USB permission callback may arrive on another thread. It should update lightweight state only and dispatch heavy work back to IO.

For a production application, use an explicit state machine:

~~~
POWER_OFF
  -> INITIALIZING
  -> PERMISSION_PENDING
  -> READY
  -> DEVICE_SELECTED
  -> SCANNER_OPEN
  -> CAPTURING
  -> SCANNER_OPEN
  -> CLOSED
~~~

Do not allow open before READY or capture before SCANNER_OPEN.

## 12. Reverse-engineering findings

The Tutorial supplied the hardware behavior pattern:

- It switches integrated fingerprint power on.
- It blinks the green LED while waiting.
- It calls scannerAutoCapture.
- It changes the LED after detection.
- It performs capture in a background task.
- It exposes scannerAutoCaptureCancel.

The official com.dermalog.android.diag package supplied the SDK API and classloader reference. Important compatibility choices are:

~~~
queryDevicesAndroid()  before queryDevices()
getId()                 before getID()
openAndroid(...)        before open(DEBDeviceEntry)
scannerAutoCapture     before scannerCapture loop
~~~

## 13. Troubleshooting table

| Symptom | Cause | Solution |
|---|---|---|
| Constructor hangs or crashes | Wrong native load, ABI, or classloader | Use official classloader, avoid manual preload, use worker thread |
| Permission succeeds but no device is found | Power rail has not published the device or USB reset is in progress | Power on, wait, then request/query |
| NoSuchMethodException - getID | Official SDK exposes getId | Support both names |
| open fails with the constructor signature | Public constructor is unavailable | Use protected openAndroid on the real Android entry |
| Capture returns without a finger | Manual scannerCapture loop | Use scannerAutoCapture(0L, ...) |
| intValue on null | scannerCapture is void in the current SDK | Read DEBCaptureInfo |
| Right LED fails | Only left sensor side is supported | Use SIDE_LEFT |
| required format is not supported | Incompatible reference template | Clear reference and recreate with format 0 |
| ANR/JNI on close | DEBDestroyHandle is unsafe | Do not call dispose |
| ADB disconnects during power | Internal USB host reset | Wait for ADB reconnect |

### Result and error contract

| Operation | Success condition | Required application behavior on failure |
|---|---|---|
| SDK constructor | Instance created within the wrapper's 15-second constructor timeout | Report constructor/ABI/classloader failure; do not continue to initialize |
| `initialize()` | Return code `0` and `isInitialized() == true` | Keep scanner actions disabled and show initialization failure |
| USB permission | Callback result `0` | Treat any non-zero result as denied/error; do not query/open as if permission succeeded |
| Device query | At least one `DEBDeviceEntry` | Clear selection and show power/USB guidance when the list is empty |
| Open | Non-null handle and successful `scannerGetImageSize()` | Do not enable capture |
| Auto-capture | `captureInfo.isDetect() == true` | Do not save a capture or extract a template when detection is false |
| BIH validation | `VALID` or the explicitly accepted diagnostic quality state | Never treat `INVALID` BIH as a valid template source |
| Template extraction | Non-empty byte array | Show template error and keep verification unavailable for that capture |
| Template verification | Numeric score returned | Display `VERIFY ERROR` for exceptions; never translate an exception into `NO MATCH` |
| Close | `uninitialize()` and power-off attempted on IO | Never call native handle `dispose()` on the tested firmware |

## 14. Future-application checklist

### Before development

- [ ] Record model, Android version, API level, and ABI.
- [ ] Identify integrated power path, VID/PID, and serial.
- [ ] Record getVersion() from the target.
- [ ] Extract native libraries from the target image.
- [ ] Keep Tutorial and official diagnostic applications available for comparison.

### Implementation

- [ ] Construct the SDK once.
- [ ] Run constructor and native calls on IO.
- [ ] Request permission after power-on and after a publish wait.
- [ ] Support getId/getID and queryDevicesAndroid/queryDevices.
- [ ] Open using the real openAndroid path.
- [ ] Allocate the BIH buffer from scannerGetImageSize().
- [ ] Use auto-capture and isDetect().
- [ ] Use the left LED side only.
- [ ] Serialize capture and close.
- [ ] Do not re-enable dispose without native testing.
- [ ] Keep VERIFY ERROR separate from NO MATCH.
- [ ] Save raw, metadata, and template files with serial and timestamp.

### Device test

1. Press Initialize; expect result 0 and isInitialized=true.
2. Press Find Device; expect one F1 entry with serial.
3. Press Open Scanner; expect 320x480 and 154664 bytes.
4. Capture without a finger; LED should blink and no capture should be saved immediately.
5. Place a finger; expect detection, image, valid BIH, and a raw file.
6. Extract a DERMALOG (0) template.
7. Use one capture as reference and another as probe.
8. Test a different finger and verify a significantly lower score.
9. Press Close Scanner after capture and verify no ANR.
10. Repeat Initialize → Find Device → Open Scanner.
11. After a power reset, wait for ADB to reconnect before collecting logs.

### Firmware evidence record

The validated baseline is Android 7.1.2 / API 25 / armeabi-v7a with SDK runtime version 2.8.1.1818. The complete Android build fingerprint was not retained in the original test notes and must be recorded for every deployment or firmware update.

Before accepting a new device image, capture and archive:

~~~powershell
adb -s <TARGET_SERIAL> shell getprop ro.build.fingerprint
adb -s <TARGET_SERIAL> shell getprop ro.build.version.incremental
adb -s <TARGET_SERIAL> shell getprop ro.product.model
adb -s <TARGET_SERIAL> shell getprop ro.product.device
adb -s <TARGET_SERIAL> shell getprop ro.product.cpu.abi
~~~

Record the returned fingerprint, SDK `getVersion()`, observed VID/PID, F1 serial, app version, and the results of the device test checklist. A firmware change invalidates the assumptions around power, native disposal, and vendor API compatibility until this checklist passes again.

## 15. Build, install, and logcat

Windows PowerShell:

~~~powershell
cd C:\Users\SB\Downloads\dermalog-f1-diagnostic-tool
.\gradlew.bat :app:assembleDebug --no-daemon --console=plain
adb devices -l
adb -s <TARGET_SERIAL> install -r app\build\outputs\apk\debug\app-debug.apk
adb -s <TARGET_SERIAL> shell am force-stop com.aistudio.dermalogf1.diag
adb -s <TARGET_SERIAL> shell am start -n com.aistudio.dermalogf1.diag/com.example.MainActivity
~~~

The target serial used in the last test was fe7b10e. Always confirm that adb devices -l reports SVT3000 SE before installing.

For a release APK, provide the release signing material outside the repository and run:

~~~powershell
$env:KEYSTORE_PATH = 'C:\secure\my-upload-key.jks'
$env:STORE_PASSWORD = '<keystore password>'
$env:KEY_PASSWORD = '<key password>'
.\gradlew.bat :app:assembleRelease --no-daemon --console=plain
~~~

The release signing values are secrets; do not place them in `.env`, Gradle files, logs, or Git.

Relevant logcat filter:

~~~powershell
adb -s <TARGET_SERIAL> logcat -c
adb -s <TARGET_SERIAL> logcat | findstr /i "dermalog embeddedbiometrics diagnostic usb zf1 scanner JNI AndroidRuntime ANR"
~~~

## 16. Important project files

~~~
app/src/main/java/com/dermalog/android/embeddedbiometrics/EmbeddedBiometricsSDK.java
    classloader, power, permission, query, and open

app/src/main/java/com/dermalog/android/embeddedbiometrics/EmbeddedBiometricsHandle.java
    capture, LED, image size, template, and verification

app/src/main/java/com/example/ui/DiagnosticViewModel.kt
    lifecycle, capture, close, and file persistence

app/src/main/java/com/example/ui/BihValidator.kt
    BIH structure and basic quality validation

app/src/main/java/com/example/ui/BihImageDecoder.kt
    BIH-to-Bitmap conversion

app/libs/README.md
    proprietary artifact and native-library list
~~~

## 17. Production limitations

This project is now a validated diagnostic/reference implementation for the SVT3000 SE. The following still require separate production design and validation:

- verification threshold calibration with a real dataset and FAR/FRR measurements;
- multiple fingers and multiple references;
- a formal Cancel action for auto-capture;
- retry and recovery after USB/power reset;
- secure logging without biometric disclosure;
- template encryption and file access control;
- raw-capture retention and deletion policy;
- native lifecycle validation on other firmware or models;
- long-running memory/resource leak testing, because the tested image deliberately skips handle disposal;
- legal and security review before real-world identity authentication.

## Executive summary

For every future application on this device, preserve these three rules:

1. The fingerprint module is an integrated peripheral whose power is controlled through the device HAL. USB permission becomes meaningful after power-on and device publication.
2. Capture must use scannerAutoCapture(0L, ...) in the background so it waits for an actual finger.
3. On the tested firmware, DEBDestroyHandle/dispose is unsafe during close. Close must skip dispose and perform uninitialize plus power-off on IO.

Following these rules keeps the complete initialize-to-capture, preview, template, and verification lifecycle stable and repeatable on the SVT3000 SE.
