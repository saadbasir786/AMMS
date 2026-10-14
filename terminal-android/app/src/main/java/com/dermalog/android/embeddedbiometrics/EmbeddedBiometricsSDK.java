package com.dermalog.android.embeddedbiometrics;

import android.content.Context;
import android.content.BroadcastReceiver;
import android.content.Intent;
import android.content.IntentFilter;
import android.hardware.usb.UsbDevice;
import android.hardware.usb.UsbManager;
import android.app.PendingIntent;
import android.util.Log;

import dalvik.system.DexClassLoader;

import com.dermalog.android.usb.IUSBPermission;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.lang.reflect.Array;
import java.lang.reflect.InvocationTargetException;
import java.lang.reflect.Proxy;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.FutureTask;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.TimeoutException;
import java.util.IdentityHashMap;
import java.util.Map;

/** Thin compile-time bridge to the vendor SDK dex shipped in assets. */
public class EmbeddedBiometricsSDK {
    private final Object real;
    private final Class<?> cls;
    private final Context appContext;
    private final Map<DEBDeviceEntry, Object> androidEntries = new IdentityHashMap<>();

    public EmbeddedBiometricsSDK(Context context) throws Exception {
        appContext = context.getApplicationContext();
        Context sdkContext = context;
        String sdkPath = null;
        String nativePath = context.getApplicationInfo().nativeLibraryDir;
        ClassLoader vendorLoader = null;
        // On the SVT3000 the installed official Diagnostic app is the vendor's
        // supported runtime. Prefer its exact APK/native directory when available.
        try {
            android.content.pm.ApplicationInfo official = context.getPackageManager()
                    .getApplicationInfo("com.dermalog.android.diag", 0);
            sdkPath = official.sourceDir;
            nativePath = official.nativeLibraryDir;
            vendorLoader = context.createPackageContext("com.dermalog.android.diag",
                    Context.CONTEXT_INCLUDE_CODE | Context.CONTEXT_IGNORE_SECURITY).getClassLoader();
        } catch (Exception ignored) {
            try {
                android.content.pm.ApplicationInfo tutorial = context.getPackageManager()
                        .getApplicationInfo("com.dermalog.hardware", 0);
                sdkPath = tutorial.sourceDir;
                nativePath = tutorial.nativeLibraryDir;
                vendorLoader = context.createPackageContext("com.dermalog.hardware",
                        Context.CONTEXT_INCLUDE_CODE | Context.CONTEXT_IGNORE_SECURITY).getClassLoader();
            } catch (Exception ignoredTutorial) {
                // Development devices without vendor apps use the bundled reference APK.
            }
        }
        // SDKHelperAndroid in the reference app establishes these vendor properties
        // before the native constructor is entered.
        System.setProperty("DERMALOG_GLOBAL_SYNC_DIR", sdkContext.getCacheDir().getPath());
        System.setProperty("DERMALOG_NATIVE_LIBRARY_DIR", nativePath);
        // Do not preload the .so files here. The official Diagnostic APK uses
        // the SDK's NativeLibraryLoader; duplicate/manual loading can abort the
        // C++ runtime before initialize() is reached.

        File sdkFile = sdkPath == null
                ? new File(context.getCodeCacheDir(), "dermalog-sdk.apk")
                : new File(sdkPath);
        if (sdkPath == null && !sdkFile.exists()) {
            try (InputStream in = context.getAssets().open("dermalog-sdk.apk");
                 FileOutputStream out = new FileOutputStream(sdkFile)) {
                byte[] buffer = new byte[8192];
                int count;
                while ((count = in.read(buffer)) > 0) out.write(buffer, 0, count);
            }
        }

        final ClassLoader loader = vendorLoader != null ? vendorLoader : new DexClassLoader(
                sdkFile.getPath(), context.getCodeCacheDir().getPath(),
                nativePath, sdkContext.getClassLoader());
        // The Tutorial APK initializes its hardware Properties class before
        // constructing EmbeddedBiometricsSDK. Reproduce that class-loading step.
        try {
            Class.forName("com.dermalog.hardware.Properties", true, loader);
        } catch (ClassNotFoundException ignored) {
            // The standalone SDK APK does not contain the Tutorial helper.
        }
        final Context constructorContext = sdkContext;
        FutureTask<Object> task = new FutureTask<>(() -> {
            Class<?> sdkClass = loader.loadClass(
                    "com.dermalog.android.embeddedbiometrics.EmbeddedBiometricsSDK");
            return sdkClass.getConstructor(Context.class).newInstance(constructorContext);
        });
        Thread thread = new Thread(task, "dermalog-sdk-constructor");
        thread.start();
        try {
            real = task.get(15, TimeUnit.SECONDS);
        } catch (TimeoutException e) {
            thread.interrupt();
            throw new TimeoutException(
                    "DERMALOG SDK constructor timed out after 15 seconds");
        } catch (ExecutionException e) {
            Throwable cause = e.getCause();
            while (cause instanceof InvocationTargetException
                    && ((InvocationTargetException) cause).getCause() != null) {
                cause = ((InvocationTargetException) cause).getCause();
            }
            if (cause instanceof Exception) throw (Exception) cause;
            if (cause instanceof Error) {
                throw new RuntimeException("DERMALOG SDK constructor failed", cause);
            }
            throw e;
        }
        cls = real.getClass();
        enableFingerprintPower(real.getClass().getClassLoader(), appContext);
    }

    private static void setFingerprintPower(
            ClassLoader loader, Context appContextForPower, boolean enabled) throws Exception {
        // Exact sequence from Tutorial's PowerSwitcherTask:
        // DeviceManager.getDevice(context).getPowerManager().open();
        // powerManager.power(USB_FINGERPRINT_SCANNER, enabled).
        Class<?> deviceManager = loader.loadClass("com.dermalog.hardware.DeviceManager");
        java.lang.reflect.Method getDevice = deviceManager.getMethod(
                "getDevice", Context.class);
        Object halDevice = getDevice.invoke(null, appContextForPower);
        if (halDevice == null) {
            throw new IllegalStateException("DeviceManager.getDevice returned null");
        }

        Object powerManager = halDevice.getClass().getMethod(
                "getPowerManager").invoke(halDevice);
        if (powerManager == null) {
            throw new IllegalStateException("HALDevice.getPowerManager returned null");
        }
        powerManager.getClass().getMethod("open").invoke(powerManager);

        Class<?> powerType = loader.loadClass("com.dermalog.hardware.PowerManager$PowerType");
        Object fingerprint = java.lang.Enum.valueOf(
                (Class) powerType, "USB_FINGERPRINT_SCANNER");
        boolean supported = (Boolean) powerManager.getClass().getMethod(
                "isPowerTypeSupported", powerType).invoke(powerManager, fingerprint);
        Log.d("DermalogPower", "USB_FINGERPRINT_SCANNER supported=" + supported);
        if (!supported) {
            throw new IllegalStateException(
                    "USB_FINGERPRINT_SCANNER is not supported by HAL");
        }

        powerManager.getClass().getMethod("power", powerType, boolean.class)
                .invoke(powerManager, fingerprint, enabled);
        Log.i("DermalogPower", "USB fingerprint power switched "
                + (enabled ? "on" : "off") + " via HAL");
    }

    private static void enableFingerprintPower(ClassLoader loader, Context appContextForPower) {
        try {
            setFingerprintPower(loader, appContextForPower, true);
        } catch (Throwable error) {
            Log.e("DermalogPower", "HAL power path failed", error);
            // Compatibility fallback for older vendor images without HAL power APIs.
            try {
                System.loadLibrary("posutil");
                Class<?> posUtil = loader.loadClass(
                        "com.common.pos.api.util.posutil.PosUtil");
                Object result = posUtil.getMethod("setFingerPrintPower", int.class)
                        .invoke(null, 1);
                Log.i("DermalogPower", "PosUtil fallback returned " + result);
            } catch (Throwable fallbackError) {
                Log.e("DermalogPower", "PosUtil fallback failed", fallbackError);
            }
        }
    }

    /** Re-run the Tutorial-compatible HAL power-on sequence after a shutdown. */
    public void enableFingerprintPower() {
        enableFingerprintPower(real.getClass().getClassLoader(), appContext);
    }

    /** Explicitly switch off the integrated F1 scanner power rail. */
    public void disableFingerprintPower() throws Exception {
        try {
            setFingerprintPower(real.getClass().getClassLoader(), appContext, false);
            return;
        } catch (Throwable error) {
            Log.e("DermalogPower", "HAL power-off path failed", error);
        }

        // Compatibility fallback for older vendor images. The Tutorial used
        // the same PosUtil entry point for legacy power control.
        try {
            System.loadLibrary("posutil");
            Class<?> posUtil = real.getClass().getClassLoader().loadClass(
                    "com.common.pos.api.util.posutil.PosUtil");
            Object result = posUtil.getMethod("setFingerPrintPower", int.class)
                    .invoke(null, 0);
            Log.i("DermalogPower", "PosUtil power-off fallback returned " + result);
        } catch (Throwable fallbackError) {
            throw new IllegalStateException("Unable to switch off F1 fingerprint power",
                    fallbackError);
        }
    }

    private static void loadNativeLibraries() {
        String[] names = {
                "gnustl_shared", "usb-1.0", "usb-android-1.0", "jnidispatch",
                "system_util", "posutil", "ZF1ScanAPI", "dermalogcalibratesdk",
                "dermalogfingercode3", "dermalogembeddedbiometrics"
        };
        for (String name : names) System.loadLibrary(name);
    }

    public int initialize() throws Exception {
        Object result = cls.getMethod("initialize").invoke(real);
        return result == null ? 0 : ((Number) result).intValue();
    }

    public boolean isInitialized() throws Exception {
        return (Boolean) cls.getMethod("isInitialized").invoke(real);
    }

    public String getVersion() throws Exception {
        return String.valueOf(cls.getMethod("getVersion").invoke(real));
    }

    public void uninitialize() {
        try {
            cls.getMethod("uninitialize").invoke(real);
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }

    public void requestUSBPermissions(final IUSBPermission callback) throws Exception {
        UsbManager usb = (UsbManager) appContext.getSystemService(Context.USB_SERVICE);
        // HAL power switching is asynchronous at the USB-host level.  Tutorial
        // requests permissions only after its power task completes; on this
        // device UsbManager may need several seconds to publish the F1 device.
        UsbDevice target = waitForFingerprintDevice(usb, 8000L);
        if (target != null && usb != null && !usb.hasPermission(target)) {
            final UsbDevice requestedDevice = target;
            final String action = appContext.getPackageName() + ".USB_PERMISSION";
            final BroadcastReceiver receiver = new BroadcastReceiver() {
                @Override public void onReceive(Context context, Intent intent) {
                    if (!action.equals(intent.getAction())) return;
                    try { context.unregisterReceiver(this); } catch (Exception ignored) { }
                    boolean granted = intent.getBooleanExtra(
                            UsbManager.EXTRA_PERMISSION_GRANTED, false);
                    if (!granted) {
                        callback.permissionsResult(1);
                        return;
                    }
                    try { requestVendorUSBPermissions(callback); }
                    catch (Exception e) { callback.permissionsResult(1); }
                }
            };
            appContext.registerReceiver(receiver, new IntentFilter(action));
            PendingIntent pending = PendingIntent.getBroadcast(appContext, 1001,
                    new Intent(action).setPackage(appContext.getPackageName()), 0);
            usb.requestPermission(requestedDevice, pending);
            return;
        }
        if (target != null && usb != null && usb.hasPermission(target)) {
            requestVendorUSBPermissions(callback);
            return;
        }
        requestVendorUSBPermissions(callback);
    }

    private UsbDevice waitForFingerprintDevice(UsbManager usb, long timeoutMs) {
        if (usb == null) return null;
        long deadline = android.os.SystemClock.uptimeMillis() + timeoutMs;
        do {
            for (UsbDevice device : usb.getDeviceList().values()) {
                if (device.getVendorId() == 0x1FBA && device.getProductId() == 0x0013) {
                    Log.d("DermalogUSB", "F1 published by UsbManager: " + device.getDeviceName());
                    return device;
                }
            }
            android.os.SystemClock.sleep(250L);
        } while (android.os.SystemClock.uptimeMillis() < deadline);
        Log.w("DermalogUSB", "F1 not published by UsbManager after " + timeoutMs + " ms");
        return null;
    }

    private void requestVendorUSBPermissions(final IUSBPermission callback) throws Exception {
        Class<?> callbackType = Class.forName(
                "com.dermalog.android.usb.IUSBPermission", true,
                real.getClass().getClassLoader());
        Object proxy = Proxy.newProxyInstance(callbackType.getClassLoader(),
                new Class[]{callbackType}, (object, method, args) -> {
                    if (method.getName().equals("permissionsResult")) {
                        callback.permissionsResult((Integer) args[0]);
                    }
                    return null;
                });
        cls.getMethod("requestUSBPermissions", callbackType).invoke(real, proxy);
    }

    public DEBDeviceEntry[] queryDevices() throws Exception {
        Object array;
        try {
            array = cls.getMethod("queryDevicesAndroid").invoke(real);
        } catch (NoSuchMethodException ignored) {
            array = cls.getMethod("queryDevices").invoke(real);
        }
        int length = Array.getLength(array);
        DEBDeviceEntry[] result = new DEBDeviceEntry[length];
        for (int i = 0; i < length; i++) {
            Object device = Array.get(array, i);
            DEBDeviceEntry entry = new DEBDeviceEntry(
                    // Official 2.8.1.1818 exposes getId() (lower-case d).
                    // Older SDK builds used getID(), so retain that fallback.
                    readDeviceInt(device, "getId", "getID"),
                    (Integer) device.getClass().getMethod("getType").invoke(device),
                    (String) device.getClass().getMethod("getSerial").invoke(device));
            result[i] = entry;
            androidEntries.put(entry, device);
        }
        return result;
    }

    public EmbeddedBiometricsHandle open(DEBDeviceEntry device) throws Exception {
        Object androidDevice = androidEntries.get(device);
        if (androidDevice != null) {
            try {
                // In the official SDK openAndroid() is protected, although it
                // is the required path for DEBDeviceEntryAndroid.
                java.lang.reflect.Method openAndroid = cls.getDeclaredMethod(
                        "openAndroid", androidDevice.getClass());
                openAndroid.setAccessible(true);
                return new EmbeddedBiometricsHandle(openAndroid.invoke(real, androidDevice));
            } catch (NoSuchMethodException ignored) {
                // Fall through for SDK builds that expose only the generic API.
            }
        }
        Class<?> deviceType = Class.forName(
                "com.dermalog.android.embeddedbiometrics.DEBDeviceEntry", true,
                real.getClass().getClassLoader());
        java.lang.reflect.Constructor<?> constructor = deviceType.getDeclaredConstructor(
                int.class, int.class, String.class);
        constructor.setAccessible(true);
        Object actualDevice = constructor.newInstance(
                device.getID(), device.getType(), device.getSerial());
        return new EmbeddedBiometricsHandle(cls.getMethod("open", deviceType)
                .invoke(real, actualDevice));
    }

    private static int readDeviceInt(Object device, String preferred, String fallback)
            throws Exception {
        try {
            return ((Number) device.getClass().getMethod(preferred).invoke(device)).intValue();
        } catch (NoSuchMethodException ignored) {
            return ((Number) device.getClass().getMethod(fallback).invoke(device)).intValue();
        }
    }
}
