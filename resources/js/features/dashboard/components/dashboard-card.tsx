import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface DashboardCardProps extends ComponentProps<'section'> {
    title: string;
    description?: string;
    action?: ReactNode;
    contentClassName?: string;
}

export function DashboardCard({
    title,
    description,
    action,
    children,
    className,
    contentClassName,
    ...props
}: DashboardCardProps) {
    return (
        <section
            className={cn(
                'min-w-0 rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5',
                className,
            )}
            {...props}
        >
            <header className="flex min-w-0 items-start justify-between gap-4">
                <div className="min-w-0">
                    <h2 className="text-lg font-semibold text-admin-text-primary sm:text-xl">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-1 text-sm text-admin-text-tertiary">
                            {description}
                        </p>
                    )}
                </div>
                {action && <div className="shrink-0">{action}</div>}
            </header>
            <div className={cn('mt-4', contentClassName)}>{children}</div>
        </section>
    );
}
