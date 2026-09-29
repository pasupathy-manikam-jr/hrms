import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

// Follows Settings → Brand "Title Text": server-rendered first, then updated on every visit.
let appName =
    document
        .querySelector('meta[name="application-name"]')
        ?.getAttribute('content') || 'HRM';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome' ||
                name === 'custom-page' ||
                name.startsWith('career/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4f46e5',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Keep the brand colour in sync after Settings → Brand changes it (first paint comes from app.blade.php).
router.on('success', (event) => {
    appName = event.detail.page.props.name || appName;
    const color = event.detail.page.props.globalSettings?.primaryColor;

    if (color) {
        document.documentElement.style.setProperty('--primary', color);
        document.documentElement.style.setProperty('--ring', color);
    }
});
