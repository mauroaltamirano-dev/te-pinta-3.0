import type { PropsWithChildren } from 'react';

/**
 * Full-height panel shared by Productos (Catálogo) and General (Configuración).
 * Navigation between them comes from the admin sidebar and the module tabs.
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <section className="settings-workspace min-w-0 bg-transparent lg:h-full lg:min-h-0">
            <div
                data-settings-panel="content"
                className="min-h-[calc(100vh-10rem)] min-w-0 overflow-hidden rounded-[0.5rem] bg-admin-surface lg:h-full lg:min-h-0 lg:overflow-y-auto"
            >
                {children}
            </div>
        </section>
    );
}
