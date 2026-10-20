import { router } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { newRoundKey } from '@/features/tables/round-key';
import type { TableDetail, TableRoundKind } from '@/features/tables/types';
import { cn } from '@/lib/utils';

interface Option {
    key: string;
    name: string;
    group: string;
    /** Void mode: what the table has of it. */
    max: number;
    empanada: boolean;
}

const EXTRAS = 'Extras';

function options(detail: TableDetail, kind: TableRoundKind): Option[] {
    if (kind === 'void') {
        return (detail.session?.lines ?? []).map((line) => ({
            key:
                line.product_id !== null
                    ? `p${line.product_id}`
                    : `a${line.addon_id}`,
            name: line.name,
            group: 'En la mesa',
            max: line.quantity,
            empanada: false,
        }));
    }

    return [
        ...detail.products
            .filter((product) => product.is_sellable !== false)
            .map((product) => ({
                key: `p${product.id}`,
                name: product.name,
                group: product.category.name,
                max: 120,
                empanada: product.pricing_family === 'empanada',
            })),
        ...detail.addons
            .filter((addon) => addon.is_sellable !== false)
            .map((addon) => ({
                key: `a${addon.id}`,
                name: addon.name,
                group: EXTRAS,
                max: 120,
                empanada: false,
            })),
    ];
}

/** Draft of one round (or void) for a table; confirming sends only these lines to the kitchen. */
export function RoundComposer({ detail }: { detail: TableDetail }) {
    const session = detail.session;
    const [kind, setKind] = useState<TableRoundKind>('order');
    const [quantities, setQuantities] = useState<Record<string, number>>({});
    const [note, setNote] = useState('');
    const [group, setGroup] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [reviewing, setReviewing] = useState(false);
    const [processing, setProcessing] = useState(false);
    // One key per attempt of the same draft: a retry replays instead of printing twice.
    const roundKey = useRef<string | null>(null);

    const available = useMemo(() => options(detail, kind), [detail, kind]);
    const groups = [...new Set(available.map((option) => option.group))];
    const term = search.trim().toLocaleLowerCase('es-AR');
    const visible = available.filter(
        (option) =>
            (group === null || option.group === group) &&
            (term === '' ||
                option.name.toLocaleLowerCase('es-AR').includes(term)),
    );
    const chosen = available.filter((option) => quantities[option.key] > 0);
    const units = chosen.reduce(
        (sum, option) => sum + quantities[option.key],
        0,
    );
    const nextRound = (session?.rounds.length ?? 0) + 1;

    function change(option: Option, delta: number) {
        roundKey.current = null;
        setQuantities((current) => {
            const quantity = Math.min(
                option.max,
                Math.max(0, (current[option.key] ?? 0) + delta),
            );
            const next = { ...current };

            if (quantity === 0) {
                delete next[option.key];
            } else {
                next[option.key] = quantity;
            }

            return next;
        });
    }

    function switchKind(next: TableRoundKind) {
        roundKey.current = null;
        setKind(next);
        setQuantities({});
        setGroup(null);
        setSearch('');
    }

    function confirm() {
        roundKey.current ??= newRoundKey();
        const lines = chosen.map((option) => ({
            id: Number(option.key.slice(1)),
            quantity: quantities[option.key],
            addon: option.key.startsWith('a'),
        }));
        setProcessing(true);
        router.post(
            `/admin/tables/${detail.table.id}/rounds`,
            {
                expected_session_id: session?.id ?? null,
                kind,
                items: lines
                    .filter((line) => !line.addon)
                    .map((line) => ({
                        product_id: line.id,
                        quantity: line.quantity,
                    })),
                addons: lines
                    .filter((line) => line.addon)
                    .map((line) => ({
                        addon_id: line.id,
                        quantity: line.quantity,
                    })),
                note: note.trim() === '' ? null : note.trim(),
                round_key: roundKey.current,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    if (Object.keys(page.props.errors ?? {}).length > 0) {
                        return;
                    }

                    roundKey.current = null;
                    setQuantities({});
                    setNote('');
                    setKind('order');
                },
                onFinish: () => {
                    setProcessing(false);
                    setReviewing(false);
                },
            },
        );
    }

    return (
        <section
            aria-label="Nueva ronda"
            className="rounded-xl border border-admin-border bg-admin-surface p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-lg font-semibold">
                    {kind === 'void' ? 'Anular productos' : 'Nueva ronda'}
                </h2>
                {session && session.lines.length > 0 && (
                    <div
                        role="group"
                        aria-label="Tipo de ronda"
                        className="flex rounded-lg border border-admin-border p-0.5 text-sm"
                    >
                        {(['order', 'void'] as const).map((value) => (
                            <button
                                key={value}
                                type="button"
                                aria-pressed={kind === value}
                                onClick={() => switchKind(value)}
                                className={cn(
                                    'rounded-md px-3 py-1.5',
                                    kind === value &&
                                        'bg-admin-accent text-admin-on-accent',
                                )}
                            >
                                {value === 'order' ? 'Pedir' : 'Anular'}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {kind === 'order' && (
                <>
                    <Input
                        type="search"
                        aria-label="Buscar producto"
                        placeholder="Buscar producto"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="mt-3 h-11"
                    />
                    <nav
                        aria-label="Categorías"
                        className="-mx-1 mt-3 flex gap-2 overflow-x-auto px-1 pb-1"
                    >
                        {[null, ...groups].map((value) => (
                            <button
                                key={value ?? 'all'}
                                type="button"
                                aria-pressed={group === value}
                                onClick={() => setGroup(value)}
                                className={cn(
                                    'h-9 shrink-0 rounded-full border border-admin-border px-3 text-sm',
                                    group === value &&
                                        'border-admin-accent bg-admin-accent text-admin-on-accent',
                                )}
                            >
                                {value ?? 'Todo'}
                            </button>
                        ))}
                    </nav>
                </>
            )}

            <ul className="mt-3 divide-y divide-admin-border">
                {visible.map((option) => {
                    const quantity = quantities[option.key] ?? 0;

                    return (
                        <li
                            key={option.key}
                            className="flex items-center gap-2 py-2"
                        >
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-medium">
                                    {option.name}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {kind === 'void'
                                        ? `En la mesa: ${option.max}`
                                        : option.group}
                                </span>
                            </span>
                            {option.empanada && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-11 px-3"
                                    aria-label={`Agregar 6 ${option.name}`}
                                    onClick={() => change(option, 6)}
                                >
                                    +6
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="size-11"
                                aria-label={`Quitar 1 ${option.name}`}
                                disabled={quantity === 0}
                                onClick={() => change(option, -1)}
                            >
                                <Minus />
                            </Button>
                            <span
                                className="w-8 text-center text-lg font-semibold tabular-nums"
                                aria-label={`Cantidad de ${option.name}`}
                            >
                                {quantity}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="size-11"
                                aria-label={`Agregar 1 ${option.name}`}
                                disabled={quantity >= option.max}
                                onClick={() => change(option, 1)}
                            >
                                <Plus />
                            </Button>
                        </li>
                    );
                })}
                {visible.length === 0 && (
                    <li className="py-6 text-center text-sm text-muted-foreground">
                        No hay productos para mostrar.
                    </li>
                )}
            </ul>

            <Textarea
                aria-label="Nota para cocina"
                placeholder="Nota para cocina (opcional)"
                maxLength={300}
                value={note}
                onChange={(event) => {
                    roundKey.current = null;
                    setNote(event.target.value);
                }}
                className="mt-3"
            />

            <div className="sticky bottom-24 mt-3 md:bottom-4">
                <Button
                    type="button"
                    className="h-12 w-full text-base shadow-admin-nav-active"
                    variant={kind === 'void' ? 'destructive' : 'default'}
                    disabled={units === 0 || processing}
                    onClick={() => setReviewing(true)}
                >
                    {units === 0
                        ? 'Elegí productos'
                        : kind === 'void'
                          ? `Revisar anulación (${units})`
                          : `Revisar ronda ${nextRound} (${units})`}
                </Button>
            </div>

            <Dialog
                open={reviewing}
                onOpenChange={(open) => !processing && setReviewing(open)}
            >
                <DialogContent className="admin-shell">
                    <DialogHeader>
                        <DialogTitle>
                            {kind === 'void'
                                ? `Anular en mesa ${detail.table.code}`
                                : `Ronda ${nextRound} · mesa ${detail.table.code}`}
                        </DialogTitle>
                        <DialogDescription>
                            {kind === 'void'
                                ? 'Se resta de la cuenta y cocina recibe la anulación.'
                                : 'Al confirmar se imprime la comanda solo con estos productos.'}
                        </DialogDescription>
                    </DialogHeader>
                    <ul className="space-y-1 text-lg">
                        {chosen.map((option) => (
                            <li key={option.key}>
                                <span className="font-semibold tabular-nums">
                                    {quantities[option.key]} ×
                                </span>{' '}
                                {option.name}
                            </li>
                        ))}
                    </ul>
                    {note.trim() !== '' && (
                        <p className="text-sm">Nota: {note.trim()}</p>
                    )}
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            disabled={processing}
                            onClick={() => setReviewing(false)}
                        >
                            Volver
                        </Button>
                        <Button
                            type="button"
                            className="h-11"
                            variant={
                                kind === 'void' ? 'destructive' : 'default'
                            }
                            disabled={processing}
                            onClick={confirm}
                        >
                            {kind === 'void'
                                ? 'Confirmar anulación'
                                : 'Confirmar y enviar a cocina'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
