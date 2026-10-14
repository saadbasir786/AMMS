package com.dermalog.android.embeddedbiometrics;

import java.lang.reflect.InvocationTargetException;

public class EmbeddedBiometricsHandle {
    public static final int SIDE_LEFT = 0;
    public static final int SIDE_RIGHT = 1;
    public static final int LED_OFF = 0;
    public static final int LED_RED = 1;
    public static final int LED_GREEN = 2;
    public static final int TEMPLATE_FORMAT_DERMALOG = 0;

    private final Object real;
    private boolean disposed;

    EmbeddedBiometricsHandle(Object r) { real = r; }

    public boolean isDisposed() { return disposed; }

    public DEBImageSize scannerGetImageSize() throws Exception {
        Object x = real.getClass().getMethod("scannerGetImageSize").invoke(real);
        return new DEBImageSize(
                ((Number) x.getClass().getMethod("getWidth").invoke(x)).intValue(),
                ((Number) x.getClass().getMethod("getHeight").invoke(x)).intValue(),
                ((Number) x.getClass().getMethod("getSizeBIH").invoke(x)).intValue());
    }

    public int scannerCapture(byte[] buffer, DEBCaptureInfo info) throws Exception {
        if (info.real() == null) {
            Class<?> captureInfoClass = real.getClass().getClassLoader().loadClass(
                    "com.dermalog.android.embeddedbiometrics.DEBCaptureInfo");
            info.bind(captureInfoClass.getConstructor().newInstance());
        }

        // Official DERMALOG 2.8.1.1818 declares scannerCapture() as void and
        // writes the result into DEBCaptureInfo. Older SDKs returned an int.
        Object result = real.getClass().getMethod(
                "scannerCapture", byte[].class, info.real().getClass())
                .invoke(real, buffer, info.real());
        return result instanceof Number ? ((Number) result).intValue() : 0;
    }

    /** Tutorial-compatible native auto-capture; timeout 0 means wait for touch. */
    public void scannerAutoCapture(long timeoutMs, byte[] buffer, DEBCaptureInfo info)
            throws Exception {
        if (info.real() == null) {
            Class<?> captureInfoClass = real.getClass().getClassLoader().loadClass(
                    "com.dermalog.android.embeddedbiometrics.DEBCaptureInfo");
            info.bind(captureInfoClass.getConstructor().newInstance());
        }
        try {
            real.getClass().getMethod(
                    "scannerAutoCapture", long.class, byte[].class, info.real().getClass())
                    .invoke(real, timeoutMs, buffer, info.real());
        } catch (InvocationTargetException e) {
            Throwable cause = e.getCause();
            if (cause instanceof Exception) throw (Exception) cause;
            if (cause instanceof Error) throw (Error) cause;
            throw new IllegalStateException("Vendor scannerAutoCapture failed", cause);
        }
    }

    /** Cancel a running tutorial-compatible auto-capture operation. */
    public void scannerAutoCaptureCancel() throws Exception {
        try {
            real.getClass().getMethod("scannerAutoCaptureCancel").invoke(real);
        } catch (InvocationTargetException e) {
            Throwable cause = e.getCause();
            if (cause instanceof Exception) throw (Exception) cause;
            if (cause instanceof Error) throw (Error) cause;
            throw new IllegalStateException("Vendor scannerAutoCaptureCancel failed", cause);
        }
    }

    /** Set a scanner LED side; the single-sensor F1 uses SIDE_LEFT (0). */
    public void scannerSetLed(int side, int color) throws Exception {
        try {
            real.getClass().getMethod("scannerSetLed", int.class, int.class)
                    .invoke(real, side, color);
        } catch (InvocationTargetException e) {
            Throwable cause = e.getCause();
            if (cause instanceof Exception) throw (Exception) cause;
            if (cause instanceof Error) throw (Error) cause;
            throw new IllegalStateException("Vendor scannerSetLed failed", cause);
        }
    }

    /** Extract a vendor template from a BIH image buffer. */
    public byte[] templateExtract(byte[] bihImage, int templateFormat) throws Exception {
        try {
            Object result = real.getClass().getMethod(
                    "templateExtract", byte[].class, int.class)
                    .invoke(real, bihImage, templateFormat);
            return (byte[]) result;
        } catch (InvocationTargetException e) {
            Throwable cause = e.getCause();
            if (cause instanceof Exception) throw (Exception) cause;
            if (cause instanceof Error) throw (Error) cause;
            throw new IllegalStateException("Vendor templateExtract failed", cause);
        }
    }

    /** Verify a probe template against a reference template and return vendor score. */
    public double templateVerify(
            int referenceFormat, byte[] referenceTemplate, int probeFormat,
            byte[] probeTemplate) throws Exception {
        try {
            Object result = real.getClass().getMethod(
                    "templateVerify", int.class, byte[].class, int.class, byte[].class)
                    .invoke(real, referenceFormat, referenceTemplate, probeFormat, probeTemplate);
            return ((Number) result).doubleValue();
        } catch (InvocationTargetException e) {
            Throwable cause = e.getCause();
            if (cause instanceof Exception) throw (Exception) cause;
            if (cause instanceof Error) throw (Error) cause;
            throw new IllegalStateException("Vendor templateVerify failed", cause);
        }
    }

    public synchronized void dispose() {
        if (!disposed) {
            try {
                real.getClass().getMethod("dispose").invoke(real);
                disposed = true;
            } catch (Exception e) {
                throw new IllegalStateException(e);
            }
        }
    }
}
