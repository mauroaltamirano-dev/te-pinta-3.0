import { usePage } from '@inertiajs/react';
import { AdminSectionNavigation } from '@/components/shared/admin-section-navigation';
import { managementNavigation } from '@/features/management/navigation';

/** Section tabs shared by the ledger, Compras and Auditoría pages. */
export function ManagementSectionNavigation() {
    const { url } = usePage();
    const { items, currentHref } = managementNavigation(url);

    return (
        <AdminSectionNavigation
            label="Secciones de Gestión"
            currentUrl={url}
            activeHref={currentHref}
            items={items}
        />
    );
}
