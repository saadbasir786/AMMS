# اتصال ترمینال AMMS به Laravel

این نسخه از اپ ترمینال، SDK بایومتریک DERMALOG را حفظ می‌کند و برای تبادل اطلاعات از API پروژه Laravel استفاده می‌کند.

## Pairing ترمینال

1. مدیر، ترمینال فعال را در پنل AMMS ثبت می‌کند.
2. مدیر برای همان ترمینال یک کد چهاررقمی اتصال صادر می‌کند. کد سه دقیقه اعتبار دارد و با صدور کد تازه، کد قبلی باطل می‌شود.
3. اپراتور کد را در اپ وارد می‌کند. QR و ورود دستی Credential حذف شده‌اند.
4. Backend کد را یک‌بار مصرف می‌کند و Credential بلندمدت را به اپ می‌دهد. اپ آن را در Android Keystore ذخیره می‌کند.

نشانی API هنگام Build تنظیم می‌شود. مقدار پیش‌فرض `http://127.0.0.1:8000` فقط برای اجرای محلی روی خود دستگاه است؛ برای شبکهٔ هات‌اسپات از `-PammsApiBaseUrl=http://192.168.137.1:8000` استفاده کنید. آدرس Build روی URL ذخیره‌شده در Pairing اولویت دارد؛ بنابراین با نصب Build تازه، نیازی به Pair مجدد نیست و Credential موجود حفظ می‌شود.

## آزمون از طریق هات‌اسپات ویندوز

1. ترمینال SVT3000 را به هات‌اسپات همین کمپیوتر وصل کنید. آدرس سمت کمپیوتر در پیکربندی فعلی `192.168.137.1` است.
2. سرور Laravel را روی همان رابط شبکه اجرا کنید؛ گوش‌دادن فقط روی `127.0.0.1` برای موبایل قابل دسترسی نیست. پورت `8000` باید فقط از زیرشبکهٔ هات‌اسپات مجاز باشد.
3. Build آزمایشی را با آدرس هات‌اسپات بسازید:

```powershell
.\gradlew.bat assembleDebug -PammsApiBaseUrl=http://192.168.137.1:8000
```

هات‌اسپات ویندوز ارتباط موبایل و کمپیوتر را در شبکهٔ محلی فراهم می‌کند؛ برای محیط عملیاتی، HTTPS و قواعد فایروال محدود به شبکهٔ ترمینال لازم است.

Endpointهای Pairing:

```text
POST /api/terminals/{terminal}/pairing-code  (پنل دارای احراز هویت)
POST /api/terminal/v1/pair                   (کد یک‌بارمصرف؛ حداکثر ۵ درخواست در دقیقه)
```

## جریان جلسه و حضور

```text
GET  /api/terminal/v1/assignments/current
POST /api/terminal/v1/assignments/{assignment}/activate
GET  /api/terminal/v1/sessions/{session}/manifest
GET  /api/terminal/v1/sessions/{session}/biometric-candidates
POST /api/terminal/v1/sessions/{session}/attendance/fingerprint
POST /api/terminal/v1/sessions/{session}/close
```

قالب‌های DERMALOG هنگام انتقال JSON به Base64 تبدیل می‌شوند و در پایگاه داده به‌صورت بایت خام ذخیره می‌گردند. حضور فقط پس از Match واقعی و با `matched_template_uuid` و `candidate_set_checksum` ثبت می‌شود؛ Enrollment به‌تنهایی حضور ایجاد نمی‌کند.

ترمینال SVT3000 SE با Android API 25 و ABI `armeabi-v7a` ساخته می‌شود.
