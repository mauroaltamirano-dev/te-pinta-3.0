import type { ReactNode } from 'react';

export function AdminEmptyState({
    title,
    description,
    action,
    secondaryAction,
    icon,
}: {
    title: string;
    description: ReactNode;
    action?: ReactNode;
    secondaryAction?: ReactNode;
    icon?: ReactNode;
}) {
    return (
        <section className="admin-ui admin-empty-state" role="status">
            {icon && (
                <span className="admin-empty-icon" aria-hidden="true">
                    {icon}
                </span>
            )}
            <h2 className="admin-section-title">{title}</h2>
            <p>{description}</p>
            {(action || secondaryAction) && (
                <div className="admin-page-actions">
                    {action}
                    {secondaryAction}
                </div>
            )}
        </section>
    );
}
