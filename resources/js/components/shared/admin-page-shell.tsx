import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import type { BreadcrumbItem } from '@/types';

interface PageHeaderProps {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
}

export function AdminPageHeader({
    title,
    description,
    actions,
}: PageHeaderProps) {
    return (
        <header className="admin-ui admin-page-header">
            <div className="admin-page-heading">
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </div>
            {actions && <div className="admin-page-actions">{actions}</div>}
        </header>
    );
}

export function AdminPageShell({
    title,
    description,
    actions,
    breadcrumbs,
    navigation,
    children,
}: PageHeaderProps & {
    breadcrumbs?: BreadcrumbItem[];
    navigation?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="admin-page-shell" aria-label={title}>
            {!!breadcrumbs?.length && (
                <div className="admin-ui admin-page-breadcrumbs">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            )}
            <AdminPageHeader
                title={title}
                description={description}
                actions={actions}
            />
            {navigation}
            <div className="admin-page-content">{children}</div>
        </section>
    );
}
