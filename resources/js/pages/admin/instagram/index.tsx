import { Head, router } from '@inertiajs/react';
import {
    CalendarClock,
    Clock3,
    EllipsisVertical,
    Eye,
    ImagePlus,
    Instagram,
    Pencil,
    Plus,
    Send,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { AssetDialog } from '@/features/instagram/components/asset-dialog';
import { InstagramSwitch } from '@/features/instagram/components/instagram-switch';
import { PublicationHistory } from '@/features/instagram/components/publication-history';
import { ScheduleDialog } from '@/features/instagram/components/schedule-dialog';
import {
    categoryLabel,
    describeDays,
    formatPublicationDate,
    formatRelativeRun,
    isStoryRatio,
} from '@/features/instagram/presentation';
import { instagramRoutes } from '@/features/instagram/routes';
import type {
    InstagramPageProps,
    InstagramStoryAsset,
    InstagramStorySchedule,
} from '@/features/instagram/types';
import { cn } from '@/lib/utils';

export default function InstagramAutomation({
    automation,
    schedules,
    assets,
    publications,
    publication_filters = {},
    publication_pagination,
    operational_summary,
    pending_publications_count = 0,
}: InstagramPageProps) {
    const [automationSaving, setAutomationSaving] = useState(false);
    const [scheduleDialog, setScheduleDialog] =
        useState<InstagramStorySchedule | null>();
    const [assetDialog, setAssetDialog] =
        useState<InstagramStoryAsset | null>();
    const [manualQueueOpen, setManualQueueOpen] = useState(false);
    const [manualAssetId, setManualAssetId] = useState('');
    const [manualSaving, setManualSaving] = useState(false);
    const [assetDeleteTarget, setAssetDeleteTarget] =
        useState<InstagramStoryAsset | null>(null);
    const [assetPreview, setAssetPreview] =
        useState<InstagramStoryAsset | null>(null);
    const activeAssets = assets.filter((asset) => asset.is_active);

    const toggleAutomation = (enabled: boolean) => {
        setAutomationSaving(true);
        router.patch(
            instagramRoutes.automation,
            { enabled },
            {
                preserveScroll: true,
                onFinish: () => setAutomationSaving(false),
            },
        );
    };
    const toggleAsset = (asset: InstagramStoryAsset, isActive: boolean) =>
        router.patch(
            instagramRoutes.asset(asset.id),
            {
                name: asset.name,
                category: asset.category,
                is_active: isActive,
            },
            { preserveScroll: true },
        );
    const removeAsset = () => {
        if (!assetDeleteTarget?.can_delete) {
            return;
        }

        router.delete(instagramRoutes.asset(assetDeleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setAssetDeleteTarget(null),
        });
    };
    const queueManual = () => {
        if (!manualAssetId) {
            return;
        }

        setManualSaving(true);
        router.post(
            instagramRoutes.publications,
            { asset_id: Number(manualAssetId) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setManualQueueOpen(false);
                    setManualAssetId('');
                },
                onFinish: () => setManualSaving(false),
            },
        );
    };
    const manualAsset = activeAssets.find(
        (asset) => asset.id.toString() === manualAssetId,
    );
    const schedulesByTime = schedules.reduce<
        Record<string, InstagramStorySchedule[]>
    >((groups, schedule) => {
        if (schedule.is_active) {
            (groups[schedule.time] ??= []).push(schedule);
        }

        return groups;
    }, {});

    return (
        <>
            <Head title="Instagram" />
            <section className="grid gap-6 font-orders">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.16em] text-admin-accent-text uppercase">
                            Marketing
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-admin-text-primary">
                            Instagram
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-admin-text-tertiary">
                            Prepará las historias de cada día. Te Pinta crea la
                            cola y n8n se encarga de publicarlas.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 rounded-xl border-admin-border bg-admin-surface text-admin-text-primary hover:bg-admin-surface-raised"
                        disabled={activeAssets.length === 0}
                        onClick={() => setManualQueueOpen(true)}
                    >
                        <Send /> Enviar a cola
                    </Button>
                </header>

                <section
                    aria-labelledby="automation-title"
                    className="grid gap-5 rounded-xl border border-admin-border bg-admin-surface p-5 lg:grid-cols-[1fr_auto] lg:items-center"
                >
                    <div className="flex items-start gap-4">
                        <div
                            className={cn(
                                'flex size-11 shrink-0 items-center justify-center rounded-xl',
                                automation.effective_enabled
                                    ? 'bg-admin-action text-admin-accent-text'
                                    : 'bg-admin-surface-raised text-admin-text-tertiary',
                            )}
                        >
                            <Instagram className="size-5" />
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h2
                                    id="automation-title"
                                    className="text-lg font-semibold text-admin-text-primary"
                                >
                                    Automatización de Instagram
                                </h2>
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        automation.effective_enabled
                                            ? 'border-success/40 bg-success-subtle text-success'
                                            : 'border-admin-border bg-admin-surface-raised text-admin-text-secondary',
                                    )}
                                >
                                    {automation.effective_enabled
                                        ? 'Activa'
                                        : 'Pausada'}
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-admin-text-secondary">
                                {!automation.available
                                    ? 'La automatización está deshabilitada en la configuración del servidor.'
                                    : automation.effective_enabled
                                      ? 'Las programaciones activas pueden generar publicaciones.'
                                      : 'No se crearán nuevas publicaciones automáticas.'}
                            </p>
                            <p className="mt-2 flex items-center gap-1.5 text-xs text-admin-text-tertiary">
                                <Clock3 className="size-3.5" /> Zona horaria:{' '}
                                {automation.timezone}
                            </p>
                            {operational_summary?.last_automatic_published_at && (
                                <p className="mt-1 text-xs text-admin-text-tertiary">
                                    Última publicación automática exitosa:{' '}
                                    {formatPublicationDate(
                                        operational_summary.last_automatic_published_at,
                                    )}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-t border-admin-border pt-4 lg:border-t-0 lg:pt-0">
                        <span className="text-sm font-semibold text-admin-text-primary">
                            {automation.effective_enabled ? 'ON' : 'OFF'}
                        </span>
                        <InstagramSwitch
                            checked={automation.enabled}
                            disabled={automationSaving || !automation.available}
                            label="Activar automatización de Instagram"
                            onCheckedChange={toggleAutomation}
                        />
                    </div>
                </section>

                <section
                    aria-labelledby="schedules-title"
                    className="grid gap-4"
                >
                    <SectionHeader
                        id="schedules-title"
                        eyebrow="Próximas historias"
                        title="Programaciones"
                        description="Los horarios se evalúan con la zona horaria configurada."
                        action={
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-admin-accent-text hover:bg-admin-action hover:text-admin-accent-text"
                                onClick={() => setScheduleDialog(null)}
                            >
                                <Plus /> Agregar
                            </Button>
                        }
                    />
                    {Object.entries(schedulesByTime)
                        .filter(([, sameTime]) => sameTime.length > 1)
                        .map(([time, sameTime]) => (
                            <p
                                key={time}
                                className="rounded-lg border border-admin-border bg-admin-surface px-3 py-2 text-sm text-admin-text-secondary"
                            >
                                <strong className="font-semibold text-admin-text-primary">
                                    {sameTime.length} historias programadas a
                                    las {time}.
                                </strong>{' '}
                                Se publicarán en cola, una detrás de otra, según
                                su orden.
                            </p>
                        ))}
                    {schedules.length > 0 ? (
                        <div className="grid gap-3 lg:grid-cols-2">
                            {schedules.map((schedule) => (
                                <button
                                    key={schedule.id}
                                    type="button"
                                    onClick={() => setScheduleDialog(schedule)}
                                    className="group grid min-h-32 grid-cols-[5rem_1fr_auto] items-center gap-4 rounded-xl border border-admin-border bg-admin-surface p-4 text-left transition-colors hover:border-admin-accent/40 focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none"
                                >
                                    {schedule.asset ? (
                                        <img
                                            src={schedule.asset.preview_url}
                                            alt=""
                                            className="aspect-[9/16] h-24 rounded-lg object-cover ring-1 ring-admin-border"
                                        />
                                    ) : (
                                        <span className="flex aspect-[9/16] h-24 items-center justify-center rounded-lg bg-admin-surface-raised text-admin-text-tertiary">
                                            <ImagePlus className="size-5" />
                                        </span>
                                    )}
                                    <span className="min-w-0">
                                        <span className="flex items-center gap-2">
                                            <span className="text-2xl font-semibold text-admin-text-primary tabular-nums">
                                                {schedule.time}
                                            </span>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    schedule.is_active
                                                        ? 'border-success/40 bg-success-subtle text-success'
                                                        : 'border-admin-border bg-admin-surface-raised text-admin-text-secondary',
                                                )}
                                            >
                                                {schedule.is_active
                                                    ? 'Activa'
                                                    : 'Inactiva'}
                                            </Badge>
                                        </span>
                                        <span className="mt-1 block truncate text-sm font-semibold text-admin-text-primary">
                                            {schedule.name}
                                        </span>
                                        <span className="mt-1 block truncate text-xs text-admin-text-tertiary">
                                            {schedule.asset?.name ??
                                                'Sin imagen'}{' '}
                                            ·{' '}
                                            {describeDays(
                                                schedule.days_of_week,
                                            )}
                                        </span>
                                        <span className="mt-1 block text-xs text-admin-text-tertiary">
                                            {formatRelativeRun(
                                                schedule.next_run_at,
                                            )
                                                ? `Próxima: ${formatRelativeRun(schedule.next_run_at)}`
                                                : 'Próxima ejecución no disponible'}
                                            {schedulesByTime[schedule.time]
                                                ?.length > 1 &&
                                                ` · Orden ${
                                                    schedulesByTime[
                                                        schedule.time
                                                    ].findIndex(
                                                        (item) =>
                                                            item.id ===
                                                            schedule.id,
                                                    ) + 1
                                                }`}
                                        </span>
                                        {!schedule.asset && (
                                            <span className="mt-2 flex items-center gap-1 text-xs text-warning">
                                                <TriangleAlert className="size-3.5" />
                                                Necesita una imagen
                                            </span>
                                        )}
                                    </span>
                                    <Pencil className="size-4 text-admin-text-tertiary group-hover:text-admin-accent-text" />
                                </button>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            icon={CalendarClock}
                            title="Todavía no hay programaciones"
                            description="Creá el primer horario para empezar a preparar la cola diaria."
                            actionLabel="Crear programación"
                            onAction={() => setScheduleDialog(null)}
                        />
                    )}
                </section>

                <section aria-labelledby="library-title" className="grid gap-4">
                    <SectionHeader
                        id="library-title"
                        eyebrow="Contenido disponible"
                        title="Biblioteca"
                        description={`${assets.length} ${assets.length === 1 ? 'historia cargada' : 'historias cargadas'}`}
                        action={
                            <Button
                                type="button"
                                onClick={() => setAssetDialog(null)}
                                className="h-11 rounded-xl"
                            >
                                <ImagePlus /> Subir historia
                            </Button>
                        }
                    />
                    {assets.length > 0 ? (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                            {assets.map((asset) => (
                                <article
                                    key={asset.id}
                                    className="group overflow-hidden rounded-xl border border-admin-border bg-admin-surface"
                                >
                                    <div className="relative aspect-[9/16] bg-admin-surface-raised">
                                        <img
                                            src={asset.preview_url}
                                            alt={`Vista previa de ${asset.name}`}
                                            className={cn(
                                                'size-full object-cover',
                                                !asset.is_active &&
                                                    'opacity-45 grayscale',
                                            )}
                                        />
                                        <div className="absolute top-2 right-2">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        size="icon"
                                                        aria-label={`Acciones para ${asset.name}`}
                                                        className="size-9 rounded-lg border border-admin-border bg-overlay/65 text-on-overlay hover:bg-overlay/80"
                                                    >
                                                        <EllipsisVertical />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setAssetPreview(
                                                                asset,
                                                            )
                                                        }
                                                    >
                                                        <Eye /> Ver imagen
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setAssetDialog(
                                                                asset,
                                                            )
                                                        }
                                                    >
                                                        <Pencil /> Renombrar o
                                                        reemplazar
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            toggleAsset(
                                                                asset,
                                                                !asset.is_active,
                                                            )
                                                        }
                                                    >
                                                        {asset.is_active
                                                            ? 'Desactivar'
                                                            : 'Activar'}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onSelect={() =>
                                                            setAssetDeleteTarget(
                                                                asset,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 /> Eliminar
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                        {!isStoryRatio(
                                            asset.width,
                                            asset.height,
                                        ) && (
                                            <span className="absolute bottom-2 left-2 flex items-center gap-1 rounded-md bg-warning-subtle px-2 py-1 text-[0.6875rem] font-medium text-warning">
                                                <TriangleAlert className="size-3" />{' '}
                                                No es 9:16
                                            </span>
                                        )}
                                    </div>
                                    <div className="grid gap-3 p-3">
                                        <div className="min-w-0">
                                            <h3 className="truncate text-sm font-semibold text-admin-text-primary">
                                                {asset.name}
                                            </h3>
                                            <p className="mt-1 truncate text-xs text-admin-text-tertiary">
                                                {categoryLabel(asset.category)}{' '}
                                                · {asset.width}×{asset.height}
                                            </p>
                                            {(asset.active_schedules_count ??
                                                0) > 0 && (
                                                <p className="mt-1 truncate text-xs text-warning">
                                                    En uso por{' '}
                                                    {
                                                        asset.active_schedules_count
                                                    }{' '}
                                                    {asset.active_schedules_count ===
                                                    1
                                                        ? 'programación'
                                                        : 'programaciones'}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs text-admin-text-secondary">
                                                {asset.is_active
                                                    ? 'Disponible'
                                                    : 'Inactiva'}
                                            </span>
                                            <InstagramSwitch
                                                checked={asset.is_active}
                                                label={`${asset.is_active ? 'Desactivar' : 'Activar'} ${asset.name}`}
                                                onCheckedChange={(checked) =>
                                                    toggleAsset(asset, checked)
                                                }
                                            />
                                        </div>
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            icon={ImagePlus}
                            title="La biblioteca está vacía"
                            description="Subí las historias diseñadas para asignarlas a una programación."
                            actionLabel="Subir primera historia"
                            onAction={() => setAssetDialog(null)}
                        />
                    )}
                </section>

                <section aria-labelledby="history-title" className="grid gap-4">
                    <SectionHeader
                        id="history-title"
                        eyebrow="Actividad reciente"
                        title="Historial"
                        description="Publicaciones automáticas y envíos manuales, sin perder la auditoría."
                    />
                    <PublicationHistory
                        publications={publications}
                        filters={publication_filters}
                        pagination={publication_pagination}
                        summary={operational_summary}
                    />
                </section>
            </section>

            {scheduleDialog !== undefined && (
                <ScheduleDialog
                    key={scheduleDialog?.id ?? 'new'}
                    open
                    schedule={scheduleDialog}
                    assets={assets}
                    onOpenChange={(open) =>
                        !open && setScheduleDialog(undefined)
                    }
                />
            )}
            {assetDialog !== undefined && (
                <AssetDialog
                    key={assetDialog?.id ?? 'new'}
                    open
                    asset={assetDialog}
                    onOpenChange={(open) => !open && setAssetDialog(undefined)}
                />
            )}
            <Dialog open={manualQueueOpen} onOpenChange={setManualQueueOpen}>
                <DialogContent className="admin-shell border-admin-border bg-admin-surface sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Enviar historia a la cola</DialogTitle>
                        <DialogDescription>
                            Se agregará a la cola de publicación. n8n la enviará
                            automáticamente a Instagram.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <label className="text-sm font-medium">Historia</label>
                        <Select
                            value={manualAssetId}
                            onValueChange={setManualAssetId}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Seleccionar imagen" />
                            </SelectTrigger>
                            <SelectContent>
                                {activeAssets.map((asset) => (
                                    <SelectItem
                                        key={asset.id}
                                        value={asset.id.toString()}
                                    >
                                        {asset.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {manualAsset && (
                        <div className="flex items-center gap-3 rounded-xl border border-admin-border bg-admin-surface-raised p-3">
                            <img
                                src={manualAsset.preview_url}
                                alt={`Vista previa de ${manualAsset.name}`}
                                className="aspect-[9/16] h-24 rounded-lg object-cover"
                            />
                            <div>
                                <p className="font-semibold text-admin-text-primary">
                                    {manualAsset.name}
                                </p>
                                <p className="mt-1 text-sm text-admin-text-tertiary">
                                    Confirmá que esta sea la historia correcta.
                                </p>
                            </div>
                        </div>
                    )}
                    {pending_publications_count > 0 && (
                        <p className="rounded-lg border border-admin-border bg-admin-surface-raised px-3 py-2 text-sm text-admin-text-secondary">
                            Hay {pending_publications_count}{' '}
                            {pending_publications_count === 1
                                ? 'historia antes'
                                : 'historias antes'}{' '}
                            en la cola.
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setManualQueueOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            disabled={!manualAssetId || manualSaving}
                            onClick={queueManual}
                        >
                            <Send /> Confirmar envío
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog
                open={Boolean(assetPreview)}
                onOpenChange={(open) => !open && setAssetPreview(null)}
            >
                <DialogContent className="admin-shell max-h-[calc(100dvh-2rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{assetPreview?.name}</DialogTitle>
                        <DialogDescription>
                            Vista completa de la historia.
                        </DialogDescription>
                    </DialogHeader>
                    {assetPreview && (
                        <img
                            src={assetPreview.preview_url}
                            alt={`Historia ${assetPreview.name}`}
                            className="mx-auto max-h-[70dvh] rounded-xl object-contain"
                        />
                    )}
                </DialogContent>
            </Dialog>
            <Dialog
                open={Boolean(assetDeleteTarget)}
                onOpenChange={(open) => !open && setAssetDeleteTarget(null)}
            >
                <DialogContent className="admin-shell border-admin-border bg-admin-surface">
                    <DialogHeader>
                        <DialogTitle>Eliminar historia</DialogTitle>
                        <DialogDescription>
                            {assetDeleteTarget?.can_delete
                                ? `¿Eliminar “${assetDeleteTarget.name}”? Esta acción no se puede deshacer.`
                                : (assetDeleteTarget?.active_schedules_count ??
                                        0) > 0
                                  ? `Esta historia está siendo usada por ${assetDeleteTarget?.active_schedules_count} programaciones.`
                                  : 'Esta historia conserva publicaciones históricas y no puede eliminarse sin destruir auditoría.'}
                        </DialogDescription>
                    </DialogHeader>
                    {!assetDeleteTarget?.can_delete && (
                        <div className="rounded-xl border border-warning/40 bg-warning-subtle p-4 text-sm text-warning">
                            {(assetDeleteTarget?.active_schedule_references
                                ?.length ?? 0) > 0 && (
                                <ul className="list-disc space-y-1 pl-5">
                                    {assetDeleteTarget?.active_schedule_references?.map(
                                        (schedule) => (
                                            <li key={schedule.id}>
                                                {schedule.name}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                            <p className="mt-2">
                                {(assetDeleteTarget?.active_schedules_count ??
                                    0) > 0
                                    ? 'Reemplazá primero la historia en esas programaciones o desactivalas. Nunca dejamos una programación activa sin imagen.'
                                    : 'Podés desactivarla para ocultarla de nuevas programaciones sin perder el historial.'}
                            </p>
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAssetDeleteTarget(null)}
                        >
                            Cancelar
                        </Button>
                        {assetDeleteTarget?.can_delete && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={removeAsset}
                            >
                                <Trash2 /> Eliminar definitivamente
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function SectionHeader({
    id,
    eyebrow,
    title,
    description,
    action,
}: {
    id: string;
    eyebrow: string;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p className="text-[0.6875rem] font-semibold tracking-[0.14em] text-admin-accent-text uppercase">
                    {eyebrow}
                </p>
                <h2
                    className="mt-1 text-xl font-semibold text-admin-text-primary"
                    id={id}
                >
                    {title}
                </h2>
                <p className="mt-1 text-sm text-admin-text-tertiary">
                    {description}
                </p>
            </div>
            {action}
        </div>
    );
}

function EmptyState({
    icon: Icon,
    title,
    description,
    actionLabel,
    onAction,
}: {
    icon: typeof CalendarClock;
    title: string;
    description: string;
    actionLabel: string;
    onAction: () => void;
}) {
    return (
        <div className="grid min-h-52 place-items-center rounded-xl border border-dashed border-admin-border bg-admin-surface p-6 text-center">
            <div className="max-w-sm">
                <span className="mx-auto flex size-11 items-center justify-center rounded-xl bg-admin-action text-admin-accent-text">
                    <Icon className="size-5" />
                </span>
                <h3 className="mt-4 font-semibold text-admin-text-primary">
                    {title}
                </h3>
                <p className="mt-1 text-sm text-admin-text-tertiary">
                    {description}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    className="mt-4 border-admin-border"
                    onClick={onAction}
                >
                    {actionLabel}
                </Button>
            </div>
        </div>
    );
}
