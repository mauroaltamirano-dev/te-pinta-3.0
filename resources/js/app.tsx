import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdminLayout from '@/layouts/admin-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';

// The restaurant name is rendered by the server, so one build serves every installation.
const restaurantName = () =>
    document
        .querySelector('meta[name="application-name"]')
        ?.getAttribute('content') ?? '';

createInertiaApp({
    title: (title) =>
        title ? `${title} - ${restaurantName()}` : restaurantName(),
    layout: (name) => {
        switch (true) {
            case name === 'public/coming-soon':
                // A standalone screen: no navigation to the closed storefront.
                return undefined;
            case name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            default:
                return AdminLayout;
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
        color: 'var(--brand-accent)',
    },
});

// Legacy browser preferences must not override the restaurant's light theme.
initializeTheme();
