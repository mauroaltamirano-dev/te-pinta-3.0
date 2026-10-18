import { router } from '@inertiajs/react';
import { Plus, Power, PowerOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { DistributionRule } from '../types';

export function DistributionRulesPanel({
    rules,
    onCreate,
    onCreateVersion,
}: {
    rules: DistributionRule[];
    onCreate: () => void;
    onCreateVersion: (rule: DistributionRule) => void;
}) {
    return (
        <section
            aria-labelledby="distribution-rules-title"
            className="min-w-0 overflow-hidden rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5"
        >
            <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2
                        id="distribution-rules-title"
                        className="text-xl font-semibold text-admin-text-primary"
                    >
                        Reglas de distribución
                    </h2>
                    <p className="mt-1 text-sm text-admin-text-tertiary">
                        Configuraciones versionadas para asignar dinero entre
                        billeteras activas.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCreate}
                    className="h-11 shrink-0"
                >
                    <Plus /> Crear regla
                </Button>
            </header>

            {rules.length === 0 ? (
                <p
                    role="status"
                    className="mt-4 rounded-lg border border-dashed border-admin-border px-4 py-8 text-center text-sm text-admin-text-tertiary"
                >
                    Todavía no hay reglas de distribución.
                </p>
            ) : (
                <div className="mt-4 grid gap-3 lg:grid-cols-2">
                    {rules.map((rule) => {
                        const version = rule.versions[0];
                        const active = rule.active ?? rule.is_active ?? false;

                        return (
                            <article
                                key={rule.id}
                                className="min-w-0 overflow-hidden rounded-lg border border-admin-border bg-admin-surface-raised p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="truncate font-medium text-admin-text-primary">
                                            {rule.name}
                                        </h3>
                                        <p className="mt-1 text-xs text-admin-text-tertiary">
                                            {version
                                                ? `Versión ${version.version_number}`
                                                : 'Sin versiones'}{' '}
                                            · {active ? 'Activa' : 'Inactiva'}
                                        </p>
                                    </div>
                                    <span
                                        className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                            active
                                                ? 'bg-admin-action text-admin-accent-text'
                                                : 'bg-admin-canvas text-admin-text-tertiary'
                                        }`}
                                    >
                                        {active ? 'Activa' : 'Inactiva'}
                                    </span>
                                </div>

                                {version && (
                                    <ul className="mt-4 grid gap-2">
                                        {version.allocations.map(
                                            (allocation) => (
                                                <li
                                                    key={
                                                        allocation.id ??
                                                        allocation.wallet.id
                                                    }
                                                    className="flex items-center justify-between gap-3 text-sm"
                                                >
                                                    <span className="min-w-0 truncate text-admin-text-secondary">
                                                        {allocation.wallet.name}
                                                    </span>
                                                    <span className="shrink-0 font-semibold text-admin-text-primary tabular-nums">
                                                        {(
                                                            allocation.percentage_bps /
                                                            100
                                                        ).toLocaleString(
                                                            'es-AR',
                                                            {
                                                                maximumFractionDigits: 2,
                                                            },
                                                        )}
                                                        %
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}

                                {rule.versions.length > 1 && (
                                    <details className="mt-4 border-t border-admin-border pt-3">
                                        <summary className="cursor-pointer text-sm font-medium text-admin-text-secondary focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none">
                                            Historial de versiones
                                        </summary>
                                        <ol className="mt-3 grid gap-2">
                                            {rule.versions.map(
                                                (historicalVersion) => (
                                                    <li
                                                        key={
                                                            historicalVersion.id
                                                        }
                                                        className="rounded-md bg-admin-canvas px-3 py-2 text-xs"
                                                    >
                                                        <p className="font-medium text-admin-text-primary">
                                                            Versión{' '}
                                                            {
                                                                historicalVersion.version_number
                                                            }
                                                        </p>
                                                        <p className="mt-1 break-words text-admin-text-tertiary">
                                                            {historicalVersion.allocations
                                                                .map(
                                                                    (
                                                                        allocation,
                                                                    ) =>
                                                                        `${allocation.wallet.name} ${(allocation.percentage_bps / 100).toLocaleString('es-AR', { maximumFractionDigits: 2 })}%`,
                                                                )
                                                                .join(' · ')}
                                                        </p>
                                                    </li>
                                                ),
                                            )}
                                        </ol>
                                    </details>
                                )}

                                <div className="mt-4 flex flex-wrap gap-2 border-t border-admin-border pt-3">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => onCreateVersion(rule)}
                                        className="h-11"
                                    >
                                        <Plus /> Nueva versión
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            router.patch(
                                                `/admin/management/distribution-rules/${rule.id}/status`,
                                                { active: !active },
                                                {
                                                    preserveScroll: true,
                                                },
                                            )
                                        }
                                        className="h-11 text-admin-text-secondary"
                                    >
                                        {active ? <PowerOff /> : <Power />}
                                        {active ? 'Desactivar' : 'Activar'}
                                    </Button>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
