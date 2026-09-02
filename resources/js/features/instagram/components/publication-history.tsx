import { router } from '@inertiajs/react';
import {
    Archive,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Eye,
    RotateCcw,
    Search,
    ShieldAlert,
    XCircle,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatPublicationDate, statusPresentation } from '../presentation';
import { instagramRoutes } from '../routes';
import type {
    InstagramHistoryFilters,
    InstagramOperationalSummary,
    InstagramPublicationPagination,
    InstagramStoryPublication,
} from '../types';

const statusOptions = [
    { value: 'all', label: 'Todas' },
    { value: 'published', label: 'Publicadas' },
    { value: 'processing', label: 'Procesando' },
    { value: 'failed', label: 'Error' },
    { value: 'cancelled', label: 'Canceladas' },
] as const;

const periodOptions = [
    { value: 'today', label: 'Hoy' },
    { value: '7_days', label: 'Últimos 7 días' },
    { value: '30_days', label: 'Últimos 30 días' },
    { value: 'all', label: 'Todo' },
] as const;

export function PublicationHistory({
    publications,
    filters,
    pagination,
    summary,
}: {
    publications: InstagramStoryPublication[];
    filters: InstagramHistoryFilters;
    pagination?: InstagramPublicationPagination;
    summary?: InstagramOperationalSummary;
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState<InstagramStoryPublication | null>(
        null,
    );
    const [archiveTarget, setArchiveTarget] =
        useState<InstagramStoryPublication | null>(null);
    const [clearOpen, setClearOpen] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);

    const navigate = (
        changes: Partial<InstagramHistoryFilters & { page: number }>,
    ) => {
        const next = { ...filters, search, ...changes };
        router.get(
            instagramRoutes.index,
            {
                ...(next.status && next.status !== 'all'
                    ? { status: next.status }
                    : {}),
                ...(next.period ? { period: next.period } : {}),
                ...(next.search ? { search: next.search } : {}),
                ...(next.show_archived ? { show_archived: 1 } : {}),
                ...(next.page && next.page > 1 ? { page: next.page } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        navigate({ search, page: 1 });
    };
    const mutate = (
        method: 'patch' | 'post',
        url: string,
        data: Record<string, number> = {},
    ) => {
        setActionError(null);
        router[method](url, data, {
            preserveScroll: true,
            onError: (errors) =>
                setActionError(
                    Object.values(errors).find(
                        (value): value is string => typeof value === 'string',
                    ) ?? 'No pudimos completar la acción.',
                ),
        });
    };
    const selectedStatus = selected
        ? statusPresentation[selected.status]
        : null;

    return (
        <>
            {summary && <OperationalSummary summary={summary} />}
            <div className="overflow-hidden rounded-xl border border-admin-border bg-admin-surface">
                <div className="grid gap-3 border-b border-admin-border p-4 lg:grid-cols-[minmax(14rem,1fr)_auto_auto_auto] lg:items-center">
                    <form
                        role="search"
                        onSubmit={submitSearch}
                        className="flex min-w-0 gap-2"
                    >
                        <div className="relative min-w-0 flex-1">
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-admin-text-tertiary"
                            />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                aria-label="Buscar por nombre de historia"
                                placeholder="Buscar historia"
                                className="h-10 border-admin-border bg-admin-canvas pl-9"
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            className="h-10 border-admin-border"
                        >
                            Buscar
                        </Button>
                    </form>
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(status) =>
                            navigate({ status, page: 1 })
                        }
                    >
                        <SelectTrigger
                            aria-label="Filtrar historial por estado"
                            className="h-10 w-full border-admin-border bg-admin-canvas lg:w-40"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {statusOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.period ?? 'all'}
                        onValueChange={(period) =>
                            navigate({ period, page: 1 })
                        }
                    >
                        <SelectTrigger
                            aria-label="Filtrar historial por período"
                            className="h-10 w-full border-admin-border bg-admin-canvas lg:w-44"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {periodOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <div className="flex items-center justify-between gap-2 lg:justify-end">
                        <label className="flex min-h-10 cursor-pointer items-center gap-2 rounded-lg px-2 text-sm text-admin-text-secondary focus-within:ring-2 focus-within:ring-admin-accent">
                            <input
                                type="checkbox"
                                checked={Boolean(filters.show_archived)}
                                onChange={(event) =>
                                    navigate({
                                        show_archived: event.target.checked,
                                        page: 1,
                                    })
                                }
                                className="size-4 accent-admin-accent"
                            />
                            Mostrar archivadas
                        </label>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setClearOpen(true)}
                            className="text-admin-text-secondary"
                        >
                            Limpiar historial
                        </Button>
                    </div>
                </div>

                {actionError && (
                    <p
                        role="alert"
                        className="border-b border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-red-200"
                    >
                        {actionError}
                    </p>
                )}

                {publications.length > 0 ? (
                    <>
                        <div className="divide-y divide-admin-border md:hidden">
                            {publications.map((publication) => (
                                <PublicationCard
                                    key={publication.id}
                                    publication={publication}
                                    onDetail={setSelected}
                                    onArchive={setArchiveTarget}
                                    onMutate={mutate}
                                />
                            ))}
                        </div>
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full min-w-[47rem] text-left text-sm">
                                <thead className="border-b border-admin-border bg-admin-surface-raised/50 text-xs text-admin-text-tertiary uppercase">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Historia
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Fecha y hora
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Estado
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-center font-medium"
                                        >
                                            Intentos
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 text-right font-medium"
                                        >
                                            <span className="sr-only">
                                                Acciones
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-admin-border">
                                    {publications.map((publication) => (
                                        <PublicationRow
                                            key={publication.id}
                                            publication={publication}
                                            onDetail={setSelected}
                                            onArchive={setArchiveTarget}
                                            onMutate={mutate}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                ) : (
                    <div
                        role="status"
                        className="grid min-h-40 place-items-center p-6 text-center"
                    >
                        <div>
                            <p className="font-semibold text-white">
                                No hay publicaciones para estos filtros
                            </p>
                            <p className="mt-1 text-sm text-admin-text-tertiary">
                                Probá otro estado, período o nombre.
                            </p>
                        </div>
                    </div>
                )}

                {pagination && pagination.last_page > 1 && (
                    <nav
                        aria-label="Paginación del historial"
                        className="flex items-center justify-between border-t border-admin-border px-4 py-3"
                    >
                        <p className="text-sm text-admin-text-tertiary">
                            Página {pagination.current_page} de{' '}
                            {pagination.last_page} · {pagination.total}{' '}
                            publicaciones
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                aria-label="Página anterior"
                                disabled={pagination.current_page <= 1}
                                onClick={() =>
                                    navigate({
                                        page: pagination.current_page - 1,
                                    })
                                }
                            >
                                <ChevronLeft />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                aria-label="Página siguiente"
                                disabled={
                                    pagination.current_page >=
                                    pagination.last_page
                                }
                                onClick={() =>
                                    navigate({
                                        page: pagination.current_page + 1,
                                    })
                                }
                            >
                                <ChevronRight />
                            </Button>
                        </div>
                    </nav>
                )}
            </div>

            <Sheet
                open={Boolean(selected)}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <SheetContent className="admin-shell dark w-full overflow-y-auto border-admin-border bg-admin-surface sm:max-w-lg">
                    {selected && (
                        <>
                            <SheetHeader className="border-b border-admin-border pr-12">
                                <SheetTitle className="text-xl text-white">
                                    Detalle de publicación
                                </SheetTitle>
                                <SheetDescription>
                                    Información operativa y trazabilidad de la
                                    historia.
                                </SheetDescription>
                            </SheetHeader>
                            <div className="grid gap-5 px-4 pb-6">
                                <div className="grid grid-cols-[5rem_1fr] gap-4 rounded-xl bg-admin-surface-raised p-3">
                                    <img
                                        src={selected.asset.preview_url}
                                        alt={`Historia ${selected.asset.name}`}
                                        className="aspect-[9/16] w-20 rounded-lg object-cover"
                                    />
                                    <div className="min-w-0">
                                        <h3 className="font-semibold text-balance text-white">
                                            {selected.asset.name}
                                        </h3>
                                        <p className="mt-1 text-sm text-admin-text-tertiary">
                                            {selected.schedule?.name ??
                                                'Envío manual'}
                                        </p>
                                        {selectedStatus && (
                                            <Badge
                                                variant="outline"
                                                className={`mt-3 ${selectedStatus.className}`}
                                            >
                                                {selected.requires_manual_review
                                                    ? 'Revisión necesaria'
                                                    : selectedStatus.label}
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                                {selected.requires_manual_review && (
                                    <ReviewWarning />
                                )}
                                {selected.error_message && (
                                    <ErrorPanel publication={selected} />
                                )}
                                <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                    <Fact
                                        label="Tipo"
                                        value={
                                            selected.type === 'automatic' ||
                                            selected.schedule
                                                ? 'Automática'
                                                : 'Manual'
                                        }
                                    />
                                    <Fact
                                        label="Intentos"
                                        value={String(selected.attempts)}
                                    />
                                    <Fact
                                        label="Fecha programada"
                                        value={formatPublicationDate(
                                            selected.scheduled_for,
                                        )}
                                    />
                                    <Fact
                                        label="Fecha reclamada"
                                        value={formatOptionalDate(
                                            selected.processing_started_at,
                                        )}
                                    />
                                    <Fact
                                        label="Fecha finalizada"
                                        value={formatOptionalDate(
                                            selected.published_at ??
                                                selected.failed_at ??
                                                selected.cancelled_at,
                                        )}
                                    />
                                    <Fact
                                        label="Reintentable"
                                        value={
                                            selected.can_retry ||
                                            selected.retryable ||
                                            selected.is_retryable
                                                ? 'Sí'
                                                : 'No'
                                        }
                                    />
                                    <Fact
                                        label="Revisión necesaria"
                                        value={
                                            selected.requires_manual_review
                                                ? 'Sí'
                                                : 'No'
                                        }
                                    />
                                </dl>
                                <TechnicalInformation publication={selected} />
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>

            <Dialog
                open={Boolean(archiveTarget)}
                onOpenChange={(open) => !open && setArchiveTarget(null)}
            >
                <DialogContent className="admin-shell dark border-admin-border bg-admin-surface">
                    <DialogHeader>
                        <DialogTitle>Archivar publicación</DialogTitle>
                        <DialogDescription>
                            Se ocultará del historial principal sin borrar su
                            auditoría. Podés verla activando “Mostrar
                            archivadas”.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setArchiveTarget(null)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                if (archiveTarget) {
                                    mutate(
                                        'patch',
                                        instagramRoutes.archivePublication(
                                            archiveTarget.id,
                                        ),
                                    );
                                }

                                setArchiveTarget(null);
                            }}
                        >
                            <Archive /> Archivar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog open={clearOpen} onOpenChange={setClearOpen}>
                <DialogContent className="admin-shell dark border-admin-border bg-admin-surface">
                    <DialogHeader>
                        <DialogTitle>Limpiar historial</DialogTitle>
                        <DialogDescription>
                            Se archivarán únicamente publicaciones finalizadas
                            anteriores a 30 días. Las pendientes, procesando o
                            con revisión necesaria no se tocarán.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setClearOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                mutate('post', instagramRoutes.archiveHistory, {
                                    days: 30,
                                });
                                setClearOpen(false);
                            }}
                        >
                            <Archive /> Archivar anteriores a 30 días
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function PublicationMenu({
    publication,
    onDetail,
    onArchive,
    onMutate,
}: PublicationItemProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Acciones para la publicación ${publication.asset.name}`}
                >
                    <EllipsisVertical />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={() => onDetail(publication)}>
                    <Eye />{' '}
                    {publication.status === 'failed'
                        ? 'Ver error'
                        : 'Ver detalle'}
                </DropdownMenuItem>
                {publication.status === 'failed' &&
                    !publication.requires_manual_review &&
                    publication.can_retry && (
                        <DropdownMenuItem
                            onSelect={() =>
                                onMutate(
                                    'post',
                                    instagramRoutes.retryPublication(
                                        publication.id,
                                    ),
                                )
                            }
                        >
                            <RotateCcw /> Reintentar
                        </DropdownMenuItem>
                    )}
                {publication.can_cancel && (
                    <DropdownMenuItem
                        onSelect={() =>
                            onMutate(
                                'post',
                                instagramRoutes.cancelPublication(
                                    publication.id,
                                ),
                            )
                        }
                    >
                        <XCircle /> Cancelar
                    </DropdownMenuItem>
                )}
                {publication.can_archive && (
                    <DropdownMenuItem onSelect={() => onArchive(publication)}>
                        <Archive /> Archivar
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

type PublicationItemProps = {
    publication: InstagramStoryPublication;
    onDetail: (publication: InstagramStoryPublication) => void;
    onArchive: (publication: InstagramStoryPublication) => void;
    onMutate: (
        method: 'patch' | 'post',
        url: string,
        data?: Record<string, number>,
    ) => void;
};

function PublicationRow(props: PublicationItemProps) {
    const { publication } = props;
    const status = statusPresentation[publication.status];

    return (
        <tr>
            <td className="px-4 py-3">
                <button
                    type="button"
                    onClick={() => props.onDetail(publication)}
                    className="flex max-w-64 items-center gap-3 rounded text-left focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none"
                >
                    <img
                        src={publication.asset.preview_url}
                        alt=""
                        className="aspect-[9/16] h-12 rounded object-cover ring-1 ring-white/10"
                    />
                    <span className="min-w-0">
                        <span className="block truncate font-semibold text-white">
                            {publication.asset.name}
                        </span>
                        <span className="mt-0.5 block truncate text-xs text-admin-text-tertiary">
                            {publication.schedule?.name ?? 'Envío manual'}
                        </span>
                    </span>
                </button>
            </td>
            <td className="px-4 py-3 text-admin-text-secondary tabular-nums">
                {formatPublicationDate(publication.scheduled_for)}
            </td>
            <td className="px-4 py-3">
                <Badge
                    variant="outline"
                    className={
                        publication.requires_manual_review
                            ? 'border-amber-400/30 bg-amber-400/10 text-amber-100'
                            : status.className
                    }
                >
                    {publication.requires_manual_review
                        ? 'Revisión necesaria'
                        : status.label}
                </Badge>
            </td>
            <td className="px-4 py-3 text-center text-admin-text-secondary tabular-nums">
                {publication.attempts}
            </td>
            <td className="px-4 py-3 text-right">
                <PublicationMenu {...props} />
            </td>
        </tr>
    );
}

function PublicationCard(props: PublicationItemProps) {
    const { publication } = props;
    const status = statusPresentation[publication.status];

    return (
        <article className="p-4">
            <div className="flex items-start gap-3">
                <img
                    src={publication.asset.preview_url}
                    alt=""
                    className="aspect-[9/16] h-16 rounded object-cover"
                />
                <div className="min-w-0 flex-1">
                    <h3 className="truncate font-semibold text-white">
                        {publication.asset.name}
                    </h3>
                    <p className="mt-1 text-xs text-admin-text-tertiary tabular-nums">
                        {formatPublicationDate(publication.scheduled_for)}
                    </p>
                    <Badge
                        variant="outline"
                        className={`mt-2 ${publication.requires_manual_review ? 'border-amber-400/30 bg-amber-400/10 text-amber-100' : status.className}`}
                    >
                        {publication.requires_manual_review
                            ? 'Revisión necesaria'
                            : status.label}
                    </Badge>
                </div>
                <PublicationMenu {...props} />
            </div>
            {publication.requires_manual_review && (
                <p className="mt-3 text-xs text-amber-100">
                    Meta pudo haber recibido esta publicación. Revisala antes de
                    volver a enviarla para evitar duplicados.
                </p>
            )}
        </article>
    );
}

function ReviewWarning() {
    return (
        <div className="flex gap-3 rounded-xl border border-amber-400/25 bg-amber-400/10 p-3 text-sm text-amber-100">
            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
            <p>
                <strong>Revisión necesaria.</strong> Meta pudo haber recibido
                esta publicación. Revisala antes de volver a enviarla para
                evitar duplicados.
            </p>
        </div>
    );
}

function ErrorPanel({
    publication,
}: {
    publication: InstagramStoryPublication;
}) {
    return (
        <section
            aria-labelledby="publication-error-title"
            className="rounded-xl border border-red-400/20 bg-red-400/10 p-4"
        >
            <h3
                id="publication-error-title"
                className="font-semibold text-red-100"
            >
                {publication.requires_manual_review
                    ? 'Resultado incierto'
                    : publication.can_retry ||
                        publication.retryable ||
                        publication.is_retryable
                      ? 'Error reintentable'
                      : 'Error no reintentable'}
            </h3>
            <p className="mt-2 text-sm text-pretty text-red-100">
                {readableError(publication)}
            </p>
            <p className="mt-2 text-xs text-red-200/80">
                {publication.requires_manual_review
                    ? 'No reintentes sin comprobar primero Instagram.'
                    : publication.can_retry
                      ? 'Podés reintentarlo de forma segura desde el menú de la publicación.'
                      : 'Revisá el detalle antes de volver a crear una publicación.'}
            </p>
        </section>
    );
}

function TechnicalInformation({
    publication,
}: {
    publication: InstagramStoryPublication;
}) {
    const values = [
        ['Mensaje original', publication.error_message],
        ['Meta container ID', publication.meta_container_id],
        ['Meta media ID', publication.meta_media_id],
        ['Código de error', publication.failure_code],
    ];

    if (!values.some(([, value]) => value)) {
        return null;
    }

    return (
        <Collapsible>
            <CollapsibleTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="w-full justify-between px-0 text-admin-text-secondary"
                >
                    {publication.error_message
                        ? 'Detalles técnicos'
                        : 'Información técnica'}{' '}
                    <ChevronDown />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <dl className="grid gap-3 rounded-xl bg-admin-surface-raised p-4">
                    {values
                        .filter(([, value]) => value)
                        .map(([label, value]) => (
                            <Fact
                                key={label}
                                label={label ?? ''}
                                value={sanitizeTechnicalText(value ?? '')}
                            />
                        ))}
                </dl>
            </CollapsibleContent>
        </Collapsible>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-admin-text-tertiary">{label}</dt>
            <dd className="mt-0.5 text-sm break-words text-white tabular-nums">
                {value}
            </dd>
        </div>
    );
}
function formatOptionalDate(value: string | null | undefined) {
    return value ? formatPublicationDate(value) : '—';
}
function sanitizeTechnicalText(value: string) {
    return value
        .replace(/Bearer\s+[^\s,;}]+/gi, 'Bearer [oculto]')
        .replace(
            /(access[_ -]?token|authorization|client[_ -]?secret)(["':=\s]+)[^\s,;}]+/gi,
            '$1$2[oculto]',
        );
}

function readableError(publication: InstagramStoryPublication) {
    const code = publication.failure_code?.toLowerCase() ?? '';

    if (code.includes('token') || code.includes('auth')) {
        return 'La conexión con Meta necesita renovarse.';
    }

    if (publication.requires_manual_review) {
        return 'No se pudo confirmar si Meta completó la publicación.';
    }

    return sanitizeTechnicalText(
        publication.error_message ?? 'No se recibió un detalle del error.',
    );
}

function OperationalSummary({
    summary,
}: {
    summary: InstagramOperationalSummary;
}) {
    const items = [
        ['Pendientes', String(summary.pending)],
        ['Procesando', String(summary.processing)],
        ['Errores hoy', String(summary.errors_today)],
        [
            'Última publicada',
            summary.last_published_at
                ? new Intl.DateTimeFormat('es-AR', {
                      hour: '2-digit',
                      minute: '2-digit',
                  }).format(new Date(summary.last_published_at))
                : '—',
        ],
    ];

    return (
        <dl
            aria-label="Resumen operativo"
            className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-admin-border bg-admin-border sm:grid-cols-4"
        >
            {items.map(([label, value]) => (
                <div key={label} className="bg-admin-surface px-4 py-3">
                    <dt className="text-xs text-admin-text-tertiary">
                        {label}
                    </dt>
                    <dd className="mt-1 font-semibold text-white tabular-nums">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
