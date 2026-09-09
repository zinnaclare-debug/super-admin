# Android APK build

The Android app connects to https://projectschool.lyt.com.ng in mobile builds.

Prerequisites:

- JDK 21 with JAVA_HOME configured.
- Android Studio with Android SDK Platform and Build-Tools installed.

Build a debug APK:

```powershell
cd school-portal-frontend
npm install
npm run android:sync
.\android\gradlew.bat assembleDebug
```

APK output:

```text
android\app\build\outputs\apk\debug\app-debug.apk
```

Server CORS:

Set the server environment value so the Android WebView can call the API:

```env
CORS_ALLOWED_ORIGINS=http://localhost:5173,https://localhost
```

Then clear and rebuild Laravel configuration:

```bash
php artisan optimize:clear
php artisan config:cache
```
