import { Head, Link, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/features/inventory/components/form-field';
import {
    formatInventoryQuantity,
    inventoryUnitName,
} from '@/features/inventory/presentation';
import { ManagementSectionNavigation } from '@/features/management/components/management-section-navigation';
import { purchaseLineSubtotal } from '@/features/purchases/presentation';
import type { PurchaseOptions } from '@/features/purchases/types';
import { formatMoney } from '@/lib/format';
interface Line {
    inventory_item_id: string;
    input_unit_id: string;
    purchase_presentation_id: string;
    quantity: string;
    price_mode: 'total' | 'unit';
    price_amount: string;
}
const selectClass =
    'h-10 w-full min-w-0 rounded-md border bg-admin-canvas px-3 text-sm';
const cellLabelClass = 'text-xs text-admin-text-secondary lg:sr-only';
const lineColumns =
    'lg:grid-cols-[minmax(10rem,2fr)_minmax(9rem,1.5fr)_6rem_minmax(9rem,1.3fr)_7rem_7rem_2.75rem]';
// Shown next to their own field; everything else goes to the summary.
const inlineErrorKeys = new Set([
    'wallet_id',
    'effective_date',
    'supplier_name',
    'description',
    'shipping_amount',
]);

function CellError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} className="text-xs text-destructive" role="alert">
            {message}
        </p>
    ) : null;
}

export default function CreatePurchase({
    business_today,
    wallets,
    inventory_items,
    units,
}: PurchaseOptions) {
    const blank = (): Line => ({
        inventory_item_id: '',
        input_unit_id: '',
        purchase_presentation_id: '',
        quantity: '',
        price_mode: 'total',
        price_amount: '',
    });
    const form = useForm({
        idempotency_key: crypto.randomUUID(),
        wallet_id: String(wallets[0]?.id ?? ''),
        effective_date: business_today,
        supplier_name: '',
        description: '',
        shipping_amount: '0',
        lines: [blank()],
    });
    const update = (index: number, attributes: Partial<Line>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, i) =>
                i === index ? { ...line, ...attributes } : line,
            ),
        );
    const errors: Record<string, string> = form.errors;
    const subtotals = form.data.lines.map((line) =>
        purchaseLineSubtotal(line.quantity, line.price_mode, line.price_amount),
    );
    const itemsTotal = subtotals.reduce<number>(
        (sum, value) => (typeof value === 'number' ? sum + value : sum),
        0,
    );
    const pendingLines = subtotals.filter((value) => value === null).length;
    const shipping = /^\d{1,12}$/.test(form.data.shipping_amount.trim())
        ? Number(form.data.shipping_amount)
        : 0;
    const summaryErrors = Object.entries(errors).filter(
        ([key]) => !inlineErrorKeys.has(key) && !/^lines\.\d+/.test(key),
    );
    const hasErrors = Object.keys(errors).length > 0;

    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Registrar compra" />
            <Link href="/admin/management/purchases">Volver a Compras</Link>
            <h1 className="text-2xl font-semibold text-balance">
                Registrar compra
            </h1>
            <p className="max-w-3xl text-admin-text-secondary">
                Cargá cada insumo que compraste con su cantidad y precio. El
                total sale de la billetera elegida y suma stock a los insumos
                que lo controlan. El envío se suma al total, pero no al costo de
                los insumos. Los montos van en pesos, sin centavos.
            </p>
            <ManagementSectionNavigation />
            {(!wallets.length || !inventory_items.length) && (
                <p>
                    Necesitás una billetera activa y al menos un insumo activo.{' '}
                    <Link href="/admin/management">Configurar billeteras</Link>{' '}
                    · <Link href="/admin/inventory">Configurar insumos</Link>
                </p>
            )}
            <form
                className="grid gap-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/management/purchases');
                }}
            >
                <div className="grid gap-4 lg:grid-cols-3">
                    <FormField
                        id="purchase-wallet"
                        label="Billetera"
                        error={errors.wallet_id}
                    >
                        <select
                            id="purchase-wallet"
                            className={selectClass}
                            required
                            value={form.data.wallet_id}
                            onChange={(e) =>
                                form.setData('wallet_id', e.target.value)
                            }
                        >
                            <option value="">Elegir billetera</option>
                            {wallets.map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.name}
                                </option>
                            ))}
                        </select>
                    </FormField>
                    <FormField
                        id="purchase-date"
                        label="Fecha"
                        error={errors.effective_date}
                    >
                        <Input
                            id="purchase-date"
                            type="date"
                            required
                            value={form.data.effective_date}
                            onChange={(e) =>
                                form.setData('effective_date', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="supplier"
                        label="Proveedor"
                        error={errors.supplier_name}
                    >
                        <Input
                            id="supplier"
                            maxLength={160}
                            value={form.data.supplier_name}
                            onChange={(e) =>
                                form.setData('supplier_name', e.target.value)
                            }
                        />
                    </FormField>
                </div>
                <FormField
                    id="description"
                    label="Descripción (opcional)"
                    error={errors.description}
                >
                    <Input
                        id="description"
                        maxLength={240}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                    />
                </FormField>
                <section
                    aria-labelledby="purchase-lines-title"
                    className="grid gap-3"
                >
                    <h2
                        id="purchase-lines-title"
                        className="text-lg font-semibold"
                    >
                        Insumos comprados
                    </h2>
                    <div
                        aria-hidden="true"
                        className={`hidden gap-3 border-b border-admin-border px-2 pb-2 text-xs font-medium text-admin-text-secondary lg:grid ${lineColumns}`}
                    >
                        <span>Insumo</span>
                        <span>Presentación</span>
                        <span>Cantidad</span>
                        <span>Tipo de precio</span>
                        <span>Precio</span>
                        <span className="text-right">Subtotal</span>
                        <span />
                    </div>
                    {form.data.lines.map((line, index) => {
                        const n = index + 1;
                        const item = inventory_items.find(
                            (i) => String(i.id) === line.inventory_item_id,
                        );
                        const presentation = item?.presentations.find(
                            (p) =>
                                String(p.id) === line.purchase_presentation_id,
                        );
                        const unit = units.find(
                            (u) => String(u.id) === line.input_unit_id,
                        );
                        const measure = presentation
                            ? presentation.name
                            : unit
                              ? inventoryUnitName(unit).toLocaleLowerCase('es')
                              : 'unidad';
                        const subtotal = subtotals[index];
                        const measureError =
                            errors[`lines.${index}.input_unit_id`] ??
                            errors[`lines.${index}.purchase_presentation_id`];

                        return (
                            <div
                                key={index}
                                role="group"
                                aria-label={`Línea ${n}`}
                                className={`grid grid-cols-2 gap-3 rounded-lg border border-admin-border bg-admin-surface p-3 lg:items-start lg:rounded-none lg:border-0 lg:border-b lg:bg-transparent lg:px-2 ${lineColumns}`}
                            >
                                <div className="col-span-2 grid gap-1 lg:col-span-1">
                                    <label
                                        htmlFor={`item-${index}`}
                                        className={cellLabelClass}
                                    >
                                        Insumo
                                    </label>
                                    <select
                                        id={`item-${index}`}
                                        aria-label={`Insumo ${n}`}
                                        className={selectClass}
                                        required
                                        value={line.inventory_item_id}
                                        onChange={(e) => {
                                            const next = inventory_items.find(
                                                (i) =>
                                                    String(i.id) ===
                                                    e.target.value,
                                            );
                                            update(index, {
                                                inventory_item_id:
                                                    e.target.value,
                                                input_unit_id: String(
                                                    next?.default_input_unit_id ??
                                                        '',
                                                ),
                                                purchase_presentation_id: '',
                                            });
                                        }}
                                    >
                                        <option value="">Elegir insumo</option>
                                        {inventory_items.map((i) => (
                                            <option key={i.id} value={i.id}>
                                                {i.name}
                                            </option>
                                        ))}
                                    </select>
                                    {item && !item.tracks_stock && (
                                        <p className="text-xs text-admin-text-tertiary">
                                            No lleva stock: sólo suma al gasto.
                                        </p>
                                    )}
                                    <CellError
                                        id={`item-${index}-error`}
                                        message={
                                            errors[
                                                `lines.${index}.inventory_item_id`
                                            ]
                                        }
                                    />
                                </div>
                                <div className="col-span-2 grid gap-1 lg:col-span-1">
                                    <label
                                        htmlFor={`measure-${index}`}
                                        className={cellLabelClass}
                                    >
                                        Presentación
                                    </label>
                                    <select
                                        id={`measure-${index}`}
                                        aria-label={`Presentación ${n}`}
                                        className={selectClass}
                                        required
                                        value={
                                            line.purchase_presentation_id
                                                ? `p:${line.purchase_presentation_id}`
                                                : line.input_unit_id
                                                  ? `u:${line.input_unit_id}`
                                                  : ''
                                        }
                                        onChange={(e) => {
                                            const [kind, id] =
                                                e.target.value.split(':');
                                            update(index, {
                                                purchase_presentation_id:
                                                    kind === 'p' ? id : '',
                                                input_unit_id:
                                                    kind === 'u' ? id : '',
                                            });
                                        }}
                                    >
                                        <option value="">
                                            Elegir presentación
                                        </option>
                                        {units
                                            .filter(
                                                (u) =>
                                                    u.family ===
                                                    item?.unit_family,
                                            )
                                            .map((u) => (
                                                <option
                                                    key={`u${u.id}`}
                                                    value={`u:${u.id}`}
                                                >
                                                    {inventoryUnitName(u)}
                                                </option>
                                            ))}
                                        {item?.presentations
                                            .filter((p) => p.is_active)
                                            .map((p) => (
                                                <option
                                                    key={`p${p.id}`}
                                                    value={`p:${p.id}`}
                                                >
                                                    {p.name} ·{' '}
                                                    {formatInventoryQuantity(
                                                        p.content_quantity,
                                                        p.content_unit,
                                                    )}
                                                </option>
                                            ))}
                                    </select>
                                    <CellError
                                        id={`measure-${index}-error`}
                                        message={measureError}
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <label
                                        htmlFor={`quantity-${index}`}
                                        className={cellLabelClass}
                                    >
                                        Cantidad
                                    </label>
                                    <Input
                                        id={`quantity-${index}`}
                                        aria-label={`Cantidad ${n}`}
                                        inputMode="decimal"
                                        required
                                        value={line.quantity}
                                        onChange={(e) =>
                                            update(index, {
                                                quantity: e.target.value,
                                            })
                                        }
                                    />
                                    <CellError
                                        id={`quantity-${index}-error`}
                                        message={
                                            errors[`lines.${index}.quantity`]
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <label
                                        htmlFor={`mode-${index}`}
                                        className={cellLabelClass}
                                    >
                                        Tipo de precio
                                    </label>
                                    <select
                                        id={`mode-${index}`}
                                        aria-label={`Tipo de precio ${n}`}
                                        className={selectClass}
                                        value={line.price_mode}
                                        onChange={(e) =>
                                            update(index, {
                                                price_mode: e.target
                                                    .value as Line['price_mode'],
                                            })
                                        }
                                    >
                                        <option value="total">
                                            Total de la línea
                                        </option>
                                        <option value="unit">
                                            Por {measure}
                                        </option>
                                    </select>
                                    <CellError
                                        id={`mode-${index}-error`}
                                        message={
                                            errors[`lines.${index}.price_mode`]
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <label
                                        htmlFor={`price-${index}`}
                                        className={cellLabelClass}
                                    >
                                        Precio
                                    </label>
                                    <Input
                                        id={`price-${index}`}
                                        aria-label={`Precio ${n}`}
                                        inputMode="numeric"
                                        required
                                        value={line.price_amount}
                                        onChange={(e) =>
                                            update(index, {
                                                price_amount: e.target.value,
                                            })
                                        }
                                    />
                                    <CellError
                                        id={`price-${index}-error`}
                                        message={
                                            errors[
                                                `lines.${index}.price_amount`
                                            ]
                                        }
                                    />
                                </div>
                                <div className="grid gap-1 lg:text-right">
                                    <span className={cellLabelClass}>
                                        Subtotal
                                    </span>
                                    <output
                                        aria-label={`Subtotal ${n}`}
                                        className="flex min-h-10 items-center font-medium tabular-nums lg:justify-end"
                                    >
                                        {typeof subtotal === 'number'
                                            ? formatMoney(subtotal)
                                            : subtotal === 'fractional'
                                              ? 'Da centavos'
                                              : '—'}
                                    </output>
                                    {subtotal === 'fractional' && (
                                        <p className="text-xs text-admin-text-tertiary">
                                            Ajustá el precio o la cantidad para
                                            que el total no tenga centavos.
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-end justify-end lg:items-start">
                                    {form.data.lines.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Quitar línea ${n}`}
                                            title={`Quitar línea ${n}`}
                                            onClick={() =>
                                                form.setData(
                                                    'lines',
                                                    form.data.lines.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    )}
                                </div>
                                {errors[`lines.${index}`] && (
                                    <p
                                        role="alert"
                                        className="col-span-2 text-sm text-destructive lg:col-span-7"
                                    >
                                        {errors[`lines.${index}`]}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                    <Button
                        type="button"
                        variant="outline"
                        className="justify-self-start"
                        disabled={form.data.lines.length >= 100}
                        onClick={() =>
                            form.setData('lines', [...form.data.lines, blank()])
                        }
                    >
                        Agregar insumo
                    </Button>
                </section>
                <FormField
                    id="shipping"
                    label="Envío"
                    error={errors.shipping_amount}
                    className="max-w-xs"
                >
                    <Input
                        id="shipping"
                        inputMode="numeric"
                        required
                        value={form.data.shipping_amount}
                        onChange={(e) =>
                            form.setData('shipping_amount', e.target.value)
                        }
                    />
                </FormField>
                <div
                    role="region"
                    aria-label="Resumen de la compra"
                    className="sticky bottom-24 z-10 grid gap-3 border-t border-admin-border bg-admin-canvas py-3 md:bottom-0 lg:flex lg:items-center lg:justify-between"
                >
                    <div className="grid gap-1">
                        <p className="flex flex-wrap items-baseline gap-x-2 tabular-nums">
                            <span>
                                Insumos{' '}
                                <strong data-testid="purchase-items-total">
                                    {formatMoney(itemsTotal)}
                                </strong>
                            </span>
                            <span aria-hidden="true">+</span>
                            <span>
                                Envío{' '}
                                <strong data-testid="purchase-shipping">
                                    {formatMoney(shipping)}
                                </strong>
                            </span>
                            <span aria-hidden="true">=</span>
                            <span className="text-lg">
                                Total{' '}
                                <strong data-testid="purchase-total">
                                    {formatMoney(itemsTotal + shipping)}
                                </strong>
                            </span>
                        </p>
                        {pendingLines > 0 && (
                            <p className="text-xs text-admin-text-tertiary">
                                {pendingLines === 1
                                    ? 'Falta completar 1 línea.'
                                    : `Faltan completar ${pendingLines} líneas.`}
                            </p>
                        )}
                        {hasErrors && (
                            <div
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                <p>Revisá los campos marcados en rojo.</p>
                                {summaryErrors.map(([key, error]) => (
                                    <p key={key}>{error}</p>
                                ))}
                            </div>
                        )}
                    </div>
                    <Button
                        disabled={
                            form.processing ||
                            !wallets.length ||
                            !inventory_items.length
                        }
                    >
                        Confirmar compra
                    </Button>
                </div>
            </form>
        </section>
    );
}
