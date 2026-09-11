# Android app release guide

Use JDK 21 for terminal Gradle builds. Java 25 is not supported by this Gradle version.

The mobile app is central: it opens with an eight-character school-code lookup at `https://lyt.com.ng`.
A school is shown only after its exact code is valid. The selected school and signed-in account remain on the device until the user logs out.

## Development build

```powershell
cd school-portal-frontend
npm install
npm run android:sync
.\android\gradlew.bat -p android assembleDebug
```

Debug APK output:

```text
android\app\build\outputs\apk\debug\app-debug.apk
```

A debug APK is for testing only. Android/Google Play Protect can show a warning for an APK installed directly from a file because it is not distributed through Google Play and uses a debug certificate.

## Signed release APK and Play bundle

Use one private keystore for every future update. Keep its passwords and `release-keystore.jks` backed up securely; losing it prevents updates to the same Android app.

1. Create the keystore once. Choose and remember a strong password when prompted.

```powershell
cd school-portal-frontend
& "$env:JAVA_HOME\bin\keytool.exe" -genkeypair -v -keystore android\release-keystore.jks -alias lyt-school-portal -keyalg RSA -keysize 4096 -validity 10000
```

2. Create the private signing configuration. It is ignored by Git.

```powershell
Copy-Item android\keystore.properties.example android\keystore.properties
notepad android\keystore.properties
```

Set the two password values in `android\keystore.properties` to the password used for the keystore:

```properties
storeFile=release-keystore.jks
storePassword=YOUR_PRIVATE_PASSWORD
keyAlias=lyt-school-portal
keyPassword=YOUR_PRIVATE_PASSWORD
```

3. Sync and create the signed outputs.

```powershell
npm run android:sync
.\android\gradlew.bat -p android assembleRelease bundleRelease
```

Signed APK output:

```text
android\app\build\outputs\apk\release\app-release.apk
```

Google Play upload bundle:

```text
android\app\build\outputs\bundle\release\app-release.aab
```

For the most trusted installation experience, upload the `.aab` to Google Play Console and distribute it through an Internal or Closed test. A signed APK sent directly through WhatsApp, email, or Downloads can still receive a Play Protect unknown-source warning; that warning cannot be fully removed for sideloaded apps.

## Server CORS

The Android WebView uses `https://localhost`, so include it along with the platform and tenant domains:

```env
CORS_ALLOWED_ORIGINS=https://lyt.com.ng,https://www.lyt.com.ng,https://localhost
CORS_ALLOWED_ORIGIN_PATTERNS=^https\://([a-z0-9-]+).lyt.com.ng$
```

After changing server environment values:

```bash
php artisan optimize:clear
php artisan config:cache
```