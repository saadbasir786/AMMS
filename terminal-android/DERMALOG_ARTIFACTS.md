# DERMALOG runtime artifacts

The terminal module contains the runtime artifacts required for the integrated
DERMALOG F1 / ZF1 scanner on the SVT3000 SE (`armeabi-v7a`). They were imported
from the locally validated DERMALOG F1 diagnostic reference project.

Included artifacts:

- `app/src/main/jniLibs/armeabi-v7a/` — native scanner dependencies
- `app/src/main/assets/dermalog-sdk.apk` — SDK reference APK used by the wrapper fallback
- `app/src/main/java/com/dermalog/` — narrow reflection wrapper and compatibility types

Observed reference runtime version: `2.8.1.1818`.

These files are required to build and deploy the terminal. Any SDK, native
library, firmware, or signing change must be revalidated on the real device.
Do not manually load the native libraries from application code, and do not call
`EmbeddedBiometricsHandle.dispose()` unless that behaviour is revalidated.
