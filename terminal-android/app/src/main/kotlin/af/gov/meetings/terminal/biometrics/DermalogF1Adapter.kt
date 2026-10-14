package af.gov.aop.amms.terminal.biometrics

import android.content.Context
import android.util.Log
import com.dermalog.android.embeddedbiometrics.DEBCaptureInfo
import com.dermalog.android.embeddedbiometrics.DEBDeviceEntry
import com.dermalog.android.embeddedbiometrics.DEBImageSize
import com.dermalog.android.embeddedbiometrics.EmbeddedBiometricsHandle
import com.dermalog.android.embeddedbiometrics.EmbeddedBiometricsSDK
import com.dermalog.android.usb.IUSBPermission
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.cancelAndJoin
import kotlinx.coroutines.currentCoroutineContext
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import kotlin.coroutines.resume

/**
 * Exactly mirrors DiagnosticViewModel.kt from dermalog-f1-diagnostic-tool.
 *
 * Key design decisions matching the reference app:
 * - NO rigid state machine that blocks re-initialization.
 * - sdk and handle are nullable fields. Null == not ready.
 * - initialize() reuses existing sdk if already present (just enableFingerprintPower).
 * - capture() uses a background blinking job, cancelAndJoin pattern from DiagnosticViewModel.
 * - close() skips handle.dispose() to avoid JNI C++ abort on SVT3000 SE firmware.
 * - scannerOperationMutex prevents concurrent capture calls.
 */
class DermalogF1Adapter(context: Context) : BiometricScanner {
    private val appContext = context.applicationContext
    private val TAG = "DermalogF1Adapter"

    private var sdk: EmbeddedBiometricsSDK? = null
    private var handle: EmbeddedBiometricsHandle? = null
    private var selectedDevice: DEBDeviceEntry? = null
    private var imageSize: DEBImageSize? = null
    private var sdkVersion: String = "unknown"

    // Mirrors scannerOperationMutex in DiagnosticViewModel
    private val scannerOperationMutex = Mutex()

    override fun isOpen(): Boolean = handle != null && imageSize != null

    override suspend fun initialize(): Result<BiometricDeviceInfo> = withContext(Dispatchers.IO) {
        try {
            if (sdk == null) {
                Log.i(TAG, "new EmbeddedBiometricsSDK(context)")
                val instance = EmbeddedBiometricsSDK(appContext)
                Log.i(TAG, "SDK instance created")
                // The SDK constructor powers the sensor. Retain the instance now
                // so close() can switch it off even if initialization fails later.
                sdk = instance

                val initRes = instance.initialize()
                Log.i(TAG, "initialize() returned: $initRes")

                val permResult = requestUsbPermission(instance)
                Log.i(TAG, "USB permission result: $permResult")

                // Post-permission re-check (mirrors DiagnosticViewModel lines 249-257)
                if (!instance.isInitialized) {
                    Log.w(TAG, "isInitialized() false after USB — retrying initialize()")
                    try { instance.initialize() } catch (e: Exception) {
                        Log.e(TAG, "Retry initialize error: ${e.message}")
                    }
                }

                if (!instance.isInitialized) {
                    return@withContext Result.failure(IllegalStateException("DERMALOG SDK failed to initialize"))
                }

                sdk = instance
                sdkVersion = instance.version
                Log.i(TAG, "SDK initialized (v$sdkVersion)")
            } else {
                // Reuse SDK: just power on, like DiagnosticViewModel line 234
                Log.i(TAG, "Reusing SDK: switching integrated F1 power on")
                sdk?.enableFingerprintPower()
                val initRes = sdk?.initialize() ?: -1
                Log.i(TAG, "Reuse initialize() returned: $initRes")
            }
            Result.success(BiometricDeviceInfo(selectedDevice?.id, selectedDevice?.serial, sdkVersion))
        } catch (e: Exception) {
            Log.e(TAG, "initialize() failed: ${e.javaClass.simpleName} - ${e.message}")
            Result.failure(e)
        }
    }

    override suspend fun findDevice(): Result<BiometricDeviceInfo> = withContext(Dispatchers.IO) {
        try {
            val currentSdk = sdk ?: return@withContext Result.failure(
                IllegalStateException("SDK is not initialized")
            )
            if (!currentSdk.isInitialized) {
                return@withContext Result.failure(IllegalStateException("SDK is not initialized"))
            }
            Log.i(TAG, "queryDevices() called")
            val deviceArray = currentSdk.queryDevices()
            val devList = deviceArray?.toList() ?: emptyList()
            Log.i(TAG, "queryDevices() returned ${devList.size} device(s)")

            val device = devList.firstOrNull { it.type == DEBDeviceEntry.TYPE_ZF1 }
                ?: return@withContext Result.failure(IllegalStateException("DERMALOG F1 / ZF1 device not found"))

            selectedDevice = device
            Log.i(TAG, "Selected Device: ${device.serial} (ID: ${device.id})")
            Result.success(BiometricDeviceInfo(device.id, device.serial, sdkVersion))
        } catch (e: Exception) {
            Log.e(TAG, "queryDevices() error: ${e.message}")
            Result.failure(e)
        }
    }

    override suspend fun open(): Result<ScannerInfo> = withContext(Dispatchers.IO) {
        try {
            val currentSdk = sdk ?: return@withContext Result.failure(
                IllegalStateException("SDK is not initialized")
            )
            val dev = selectedDevice ?: return@withContext Result.failure(
                IllegalStateException("No device selected")
            )
            Log.i(TAG, "open(Device Serial: ${dev.serial}) called")
            val newHandle = currentSdk.open(dev)
            val size = newHandle.scannerGetImageSize()
            require(size.width > 0 && size.height > 0 && size.sizeBIH > 0) {
                "Invalid scanner image size"
            }
            handle = newHandle
            imageSize = size
            Log.i(TAG, "Scanner opened (${size.width}x${size.height}, BIH ${size.sizeBIH})")
            Result.success(ScannerInfo(size.width, size.height, size.sizeBIH, sdkVersion))
        } catch (e: Exception) {
            Log.e(TAG, "open() failed: ${e.message}")
            Result.failure(e)
        }
    }

    override suspend fun capture(): Result<FingerprintCapture> = withContext(Dispatchers.IO) {
        try {
            scannerOperationMutex.withLock {
                val currentHandle = handle
                val size = imageSize

                if (currentHandle == null || size == null) {
                    Log.e(TAG, "Cannot capture: Scanner is closed or handle is null")
                    return@withLock Result.failure(IllegalStateException("Scanner is not open"))
                }

                Log.i(TAG, "Allocating raw BIH buffer: ${size.sizeBIH} bytes (${size.width}x${size.height})")
                val buffer = ByteArray(size.sizeBIH)
                val captureInfo = DEBCaptureInfo()

                // Exact LED blink job from DiagnosticViewModel.startCaptureLedBlink()
                Log.i(TAG, "Capture LED: GREEN blinking; waiting for fingerprint touch...")
                var captureLedBlinkJob: Job? = kotlinx.coroutines.CoroutineScope(Dispatchers.IO).launch {
                    try {
                        while (isActive) {
                            currentHandle.scannerSetLed(EmbeddedBiometricsHandle.SIDE_LEFT, EmbeddedBiometricsHandle.LED_GREEN)
                            delay(450)
                            currentHandle.scannerSetLed(EmbeddedBiometricsHandle.SIDE_LEFT, EmbeddedBiometricsHandle.LED_OFF)
                            delay(450)
                        }
                    } catch (_: Exception) { }
                }

                Log.i(TAG, "scannerAutoCapture(0L) waiting for fingerprint touch...")
                var captureReturned = false
                try {
                    currentHandle.scannerAutoCapture(0L, buffer, captureInfo)
                    captureReturned = true
                } finally {
                    captureLedBlinkJob?.cancelAndJoin()
                    captureLedBlinkJob = null
                    if (!captureReturned) {
                        try {
                            currentHandle.scannerSetLed(EmbeddedBiometricsHandle.SIDE_LEFT, EmbeddedBiometricsHandle.LED_OFF)
                        } catch (_: Exception) { }
                    }
                }

                Log.i(TAG, "scannerAutoCapture returned; Detect: ${captureInfo.isDetect}")

                // Exact finishCaptureLedSequence from DiagnosticViewModel
                try {
                    if (captureInfo.isDetect) {
                        currentHandle.scannerSetLed(EmbeddedBiometricsHandle.SIDE_LEFT, EmbeddedBiometricsHandle.LED_GREEN)
                        delay(250)
                    }
                    currentHandle.scannerSetLed(EmbeddedBiometricsHandle.SIDE_LEFT, EmbeddedBiometricsHandle.LED_RED)
                    delay(250)
                } catch (_: Exception) { }
                finally {
                    try {
                        currentHandle.scannerSetLed(EmbeddedBiometricsHandle.SIDE_LEFT, EmbeddedBiometricsHandle.LED_OFF)
                    } catch (_: Exception) { }
                }

                val validation = BihValidator.validate(buffer, size.width, size.height, captureInfo.isDetect)
                Log.i(TAG, "BIH validation: ${validation.message}")

                Result.success(FingerprintCapture(buffer, captureInfo.isDetect, validation))
            }
        } catch (e: Exception) {
            Log.e(TAG, "scannerCapture() failed: ${e.javaClass.simpleName} - ${e.message}")
            Result.failure(e)
        }
    }

    override suspend fun cancelCapture(): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            handle?.scannerAutoCaptureCancel()
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    override suspend fun extractTemplate(capture: FingerprintCapture): Result<BiometricTemplate> = withContext(Dispatchers.IO) {
        try {
            val currentHandle = handle ?: return@withContext Result.failure(
                IllegalStateException("Scanner handle is not available")
            )
            require(capture.detected && capture.validation.valid) {
                "Capture is not valid for template extraction"
            }
            Log.i(TAG, "templateExtract(DERMALOG) called")
            val bytes = currentHandle.templateExtract(capture.bih, EmbeddedBiometricsHandle.TEMPLATE_FORMAT_DERMALOG)
            require(bytes.isNotEmpty()) { "DERMALOG returned an empty template" }
            Log.i(TAG, "templateExtract() returned ${bytes.size} bytes")
            Result.success(BiometricTemplate(bytes, EmbeddedBiometricsHandle.TEMPLATE_FORMAT_DERMALOG, sdkVersion))
        } catch (e: Exception) {
            Log.e(TAG, "templateExtract() failed: ${e.message}")
            Result.failure(e)
        }
    }

    override suspend fun verify(
        reference: BiometricTemplate,
        probe: BiometricTemplate,
        threshold: Double,
    ): Result<VerificationResult> = withContext(Dispatchers.IO) {
        try {
            val currentHandle = handle ?: return@withContext Result.success(
                VerificationResult(VerificationOutcome.VERIFY_ERROR, errorCode = "handle_null")
            )
            Log.i(TAG, "templateVerify(DERMALOG) called")
            val score = currentHandle.templateVerify(reference.format, reference.bytes, probe.format, probe.bytes)
            val outcome = if (score >= threshold) VerificationOutcome.MATCH else VerificationOutcome.NO_MATCH
            Log.i(TAG, "templateVerify completed -> $outcome (Score: $score, Threshold: $threshold)")
            Result.success(VerificationResult(outcome = outcome, score = score, threshold = threshold))
        } catch (e: Exception) {
            Log.e(TAG, "templateVerify() failed: ${e.message}")
            Result.success(VerificationResult(VerificationOutcome.VERIFY_ERROR, errorCode = e.javaClass.simpleName))
        }
    }

    override suspend fun close(): Result<Unit> = withContext(Dispatchers.IO) {
        var powerOffFailure: Exception? = null
        try {
            scannerOperationMutex.withLock {
                handle?.let {
                    try {
                        it.scannerSetLed(EmbeddedBiometricsHandle.SIDE_LEFT, EmbeddedBiometricsHandle.LED_OFF)
                    } catch (_: Exception) { }
                    // DEBDestroyHandle aborts with JNI DeleteGlobalRef on SVT3000 firmware
                    // Deliberately skip handle.dispose() — matches closeScanner() in DiagnosticViewModel L694
                    Log.w(TAG, "handle.dispose() skipped: unsafe on SVT3000 firmware")
                }

                sdk?.let {
                    Log.i(TAG, "SDK uninitialize() called")
                    try { it.uninitialize() } catch (e: Exception) {
                        Log.w(TAG, "uninitialize() warning: ${e.message}")
                    }
                    Log.i(TAG, "Integrated F1 power-off requested")
                    try { it.disableFingerprintPower() } catch (e: Exception) {
                        powerOffFailure = e
                        Log.e(TAG, "Power-off failed: ${e.message}", e)
                    }
                }
            }
        } finally {
            handle = null
            imageSize = null
            selectedDevice = null
            sdk = null
            Log.i(TAG, "Scanner closed")
        }
        powerOffFailure?.let { Result.failure<Unit>(it) } ?: Result.success(Unit)
    }

    override suspend fun recover(): Result<BiometricDeviceInfo> = withContext(Dispatchers.IO) {
        try {
            close().getOrThrow()
            initialize().getOrElse { return@withContext Result.failure(it) }
            findDevice().getOrElse { return@withContext Result.failure(it) }
            open().getOrElse { return@withContext Result.failure(it) }
            Result.success(BiometricDeviceInfo(selectedDevice?.id, selectedDevice?.serial, sdkVersion))
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    private suspend fun requestUsbPermission(instance: EmbeddedBiometricsSDK): Int =
        suspendCancellableCoroutine { continuation ->
            instance.requestUSBPermissions(object : IUSBPermission {
                override fun permissionsResult(result: Int) {
                    if (continuation.isActive) continuation.resume(result)
                }
            })
        }
}
