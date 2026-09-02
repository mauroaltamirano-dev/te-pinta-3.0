import { CalendarClock, Pencil, Power } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    featuredProductStatusLabels,
    featuredProductStatusStyles,
    formatMarketingDate,
} from '../presentation';
import type { AdminFeaturedProduct } from '../types';

export function FeaturedScheduleList({
    title,
    description,
    records,
    timezone,
    empty,
    testId,
    onEdit,
    onDisable,
}: {
    title: string;
    description: string;
    records: AdminFeaturedProduct[];
    timezone: string;
    empty: string;
    testId: string;
    onEdit: (featured: AdminFeaturedProduct) => void;
    onDisable?: (featured: AdminFeaturedProduct) => void;
}) {
    return (
        <section
            data-testid={testId}
            className="h-full min-w-0 rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5"
        >
            <header>
                <h2 className="text-xl font-semibold text-white">{title}</h2>
                <p className="mt-1 text-sm text-admin-text-tertiary">
                    {description}
                </p>
            </header>
            {records.length === 0 ? (
                <p className="mt-4 rounded-lg bg-admin-surface-raised px-4 py-7 text-center text-sm text-admin-text-secondary">
                    {empty}
                </p>
            ) : (
                <ol className="mt-4 grid gap-2">
                    {records.map((featured) => (
                        <li
                            key={featured.id}
                            className="grid min-w-0 gap-3 rounded-xl border border-admin-border bg-admin-surface-raised p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                        >
                            <div className="flex min-w-0 items-start gap-3">
                                <div className="grid size-10 shrink-0 place-items-center rounded-lg bg-admin-canvas text-admin-accent">
                                    <CalendarClock
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="truncate text-sm font-semibold text-white">
                                            {featured.public_view.product.name}
                                        </h3>
                                        <span
                                            className={cn(
                                                'inline-flex rounded-full border px-2 py-0.5 text-[0.6875rem] font-semibold',
                                                featuredProductStatusStyles[
                                                    featured.status
                                                ],
                                            )}
                                        >
                                            {
                                                featuredProductStatusLabels[
                                                    featured.status
                                                ]
                                            }
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs leading-5 text-admin-text-tertiary">
                                        {formatMarketingDate(
                                            featured.starts_at,
                                            timezone,
                                        )}{' '}
                                        →{' '}
                                        {formatMarketingDate(
                                            featured.ends_at,
                                            timezone,
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="flex justify-end gap-1">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    aria-label={`Editar recomendación de ${featured.public_view.product.name}`}
                                    onClick={() => onEdit(featured)}
                                >
                                    <Pencil aria-hidden="true" />
                                </Button>
                                {onDisable && featured.is_enabled && (
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={`Desactivar recomendación de ${featured.public_view.product.name}`}
                                        onClick={() => onDisable(featured)}
                                    >
                                        <Power aria-hidden="true" />
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
