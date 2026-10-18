import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { dashboardPeriodLabels } from '@/features/dashboard/presentation';
import type { DashboardPeriod } from '@/features/dashboard/types';

const periods = ['today', 'week', 'month', 'all'] as const;

export function PeriodSelector({
    value,
    onChange,
}: {
    value: DashboardPeriod;
    onChange: (period: DashboardPeriod) => void;
}) {
    return (
        <ToggleGroup
            type="single"
            value={value}
            onValueChange={(period) => {
                if (period) {
                    onChange(period as DashboardPeriod);
                }
            }}
            aria-label="Período del panel operativo"
            className="grid w-full grid-cols-2 rounded-xl border border-admin-border bg-admin-surface p-1 sm:flex sm:w-auto"
        >
            {periods.map((period) => (
                <ToggleGroupItem
                    key={period}
                    value={period}
                    aria-label={`Mostrar período: ${dashboardPeriodLabels[period]}`}
                    className="h-10 w-full rounded-lg px-3 text-sm text-admin-text-secondary hover:bg-admin-selection hover:text-admin-text-primary data-[state=on]:bg-admin-accent data-[state=on]:text-admin-on-accent sm:w-auto sm:min-w-20"
                >
                    {dashboardPeriodLabels[period]}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
