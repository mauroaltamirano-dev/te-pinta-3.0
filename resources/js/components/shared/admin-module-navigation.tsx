import { usePage } from '@inertiajs/react';
import { AdminSectionNavigation } from '@/components/shared/admin-section-navigation';
import {
    activeAdminEntry,
    activeAdminSection,
    visibleAdminNavigation,
} from '@/features/admin-navigation/model';

/** Section tabs of the current module, shared by all of its pages. */
export function AdminModuleNavigation() {
    const { url, props } = usePage();
    const entry = activeAdminEntry(
        visibleAdminNavigation(props.auth?.permissions ?? []),
        url,
    );
    const sections = entry?.sections ?? [];

    if (!entry || sections.length < 2) {
        return null;
    }

    return (
        <AdminSectionNavigation
            label={`Secciones de ${entry.label}`}
            currentUrl={url}
            activeHref={activeAdminSection(entry, url)?.href ?? null}
            items={sections.map(({ label, href }) => ({ label, href }))}
        />
    );
}
