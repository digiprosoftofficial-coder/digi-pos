import { Capacitor } from '@capacitor/core';

export async function initCapacitor() {
    if (!Capacitor.isNativePlatform()) {
        return;
    }

    const [{ App }, { SplashScreen }] = await Promise.all([
        import('@capacitor/app'),
        import('@capacitor/splash-screen'),
    ]);

    try {
        const { StatusBar, Style } = await import('@capacitor/status-bar');
        await StatusBar.setStyle({ style: Style.Light });
        await StatusBar.setBackgroundColor({ color: '#1b5cf5' });
    } catch {
        // Status bar plugin is optional on some platforms.
    }

    try {
        await SplashScreen.hide();
    } catch {
        // ignore
    }

    App.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack) {
            window.history.back();
        } else {
            App.exitApp();
        }
    });
}
