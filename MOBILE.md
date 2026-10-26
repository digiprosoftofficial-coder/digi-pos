# digipro POS — Android / iOS (Capacitor)

The mobile apps are the same Vue SPA as the PWA, wrapped with [Capacitor](https://capacitorjs.com/). They talk to your Laravel API over Sanctum bearer tokens (`/api/v1/*`).

## Prerequisites

- Node/npm (via Sail: `./vendor/bin/sail npm …`)
- **Android:** Android Studio, SDK 34+
- **iOS (macOS only):** Xcode 15+, CocoaPods (`sudo gem install cocoapods`)

## Build web assets for native

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build:mobile
```

Output goes to `capacitor-www/` (gitignored).

## First-time native project setup

```bash
./vendor/bin/sail npx cap add android
./vendor/bin/sail npx cap add ios   # macOS only
./vendor/bin/sail npm run mobile:sync
```

## Open in IDE

```bash
./vendor/bin/sail npm run mobile:android
./vendor/bin/sail npm run mobile:ios
```

After Vue changes, run `npm run mobile:sync` before rebuilding in Android Studio / Xcode.

## Point the app at your API

On first launch, the app asks for **Server URL** (store root, e.g. `https://pos.example.com` or `http://10.0.2.2:8088` for Android emulator → host machine Sail on port 8088).

You can change it later under **Settings → API server** (log out and back in after changing).

Optional build-time default (web + mobile dev):

```env
VITE_API_URL=http://localhost:8088
```

## Local development tips

| Environment | Server URL |
|-------------|------------|
| Android emulator | `http://10.0.2.2:8088` |
| iOS simulator | `http://localhost:8088` |
| Physical device (same Wi‑Fi) | `http://<your-lan-ip>:8088` |

Ensure Sail is up (`./vendor/bin/sail up -d`) and `APP_URL` matches what clients use. CORS is open for `api/*` in `config/cors.php` for Capacitor origins.

Cleartext HTTP is allowed in `capacitor.config.json` (`server.cleartext: true`) for local HTTP APIs. Use HTTPS in production.

## Offline

Dexie offline POS sync works in the native WebView the same as the PWA. The service worker is **not** registered in Capacitor (only in the browser PWA).

## App ID

`com.digiprosoft.pos` — change in `capacitor.config.json` before store release.
