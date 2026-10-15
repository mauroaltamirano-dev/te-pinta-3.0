import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/features/inventory/components/form-field';
import { formatInventoryQuantity } from '@/features/inventory/presentation';
import type { PurchaseOptions } from '@/features/purchases/types';
interface Line {
    inventory_item_id: string;
    input_unit_id: string;
    purchase_presentation_id: string;
    quantity: string;
    price_mode: 'total' | 'unit';
    price_amount: string;
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

    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Registrar compra" />
            <Link href="/admin/management/purchases">Volver a Compras</Link>
            <h1 className="text-2xl font-semibold text-balance">
                Registrar compra
            </h1>
            <p>
                Una compra registra el egreso completo. Sólo los insumos que
                controlan stock generan entradas físicas y capas de costo. El
                envío no se distribuye entre los insumos.
            </p>
            {(!wallets.length || !inventory_items.length) && (
                <p>
                    Necesitás una billetera activa y al menos un insumo activo.{' '}
                    <Link href="/admin/management">Configurar billeteras</Link>{' '}
                    · <Link href="/admin/inventory">Configurar insumos</Link>
                </p>
            )}
            <form
                className="grid gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/management/purchases');
                }}
            >
                <FormField
                    id="purchase-wallet"
                    label="Billetera"
                    error={errors.wallet_id}
                >
                    <select
                        id="purchase-wallet"
                        className="h-10 rounded-md border bg-admin-canvas px-3"
                        required
                        value={form.data.wallet_id}
                        onChange={(e) =>
                            form.setData('wallet_id', e.target.value)
                        }
                    >
                        <option value="">Seleccionar</option>
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
                <FormField
                    id="description"
                    label="Descripción"
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
                {form.data.lines.map((line, index) => {
                    const item = inventory_items.find(
                        (i) => String(i.id) === line.inventory_item_id,
                    );

                    return (
                        <fieldset
                            key={index}
                            className="grid gap-3 rounded-xl border border-admin-border bg-admin-surface p-4"
                        >
                            <legend>Línea {index + 1}</legend>
                            <FormField
                                id={`item-${index}`}
                                label="Insumo"
                                error={
                                    errors[`lines.${index}.inventory_item_id`]
                                }
                            >
                                <select
                                    id={`item-${index}`}
                                    className="h-10 rounded-md border bg-admin-canvas px-3"
                                    required
                                    value={line.inventory_item_id}
                                    onChange={(e) => {
                                        const next = inventory_items.find(
                                            (i) =>
                                                String(i.id) === e.target.value,
                                        );
                                        update(index, {
                                            inventory_item_id: e.target.value,
                                            input_unit_id: String(
                                                next?.default_input_unit_id ??
                                                    '',
                                            ),
                                            purchase_presentation_id: '',
                                        });
                                    }}
                                >
                                    <option value="">Seleccionar</option>
                                    {inventory_items.map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.name}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <p>
                                {item?.tracks_stock
                                    ? 'Actualiza stock y costo del insumo.'
                                    : 'No genera stock físico.'}
                            </p>
                            <FormField
                                id={`measure-${index}`}
                                label="Unidad o presentación"
                                error={
                                    errors[`lines.${index}.input_unit_id`] ??
                                    errors[
                                        `lines.${index}.purchase_presentation_id`
                                    ]
                                }
                            >
                                <select
                                    id={`measure-${index}`}
                                    className="h-10 rounded-md border bg-admin-canvas px-3"
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
                                    <option value="">Seleccionar</option>
                                    {units
                                        .filter(
                                            (u) =>
                                                u.family === item?.unit_family,
                                        )
                                        .map((u) => (
                                            <option
                                                key={`u${u.id}`}
                                                value={`u:${u.id}`}
                                            >
                                                {u.name}
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
                            </FormField>
                            <FormField
                                id={`quantity-${index}`}
                                label="Cantidad"
                                error={errors[`lines.${index}.quantity`]}
                            >
                                <Input
                                    id={`quantity-${index}`}
                                    inputMode="decimal"
                                    required
                                    value={line.quantity}
                                    onChange={(e) =>
                                        update(index, {
                                            quantity: e.target.value,
                                        })
                                    }
                                />
                            </FormField>
                            <FormField
                                id={`mode-${index}`}
                                label="Tipo de precio"
                            >
                                <select
                                    id={`mode-${index}`}
                                    className="h-10 rounded-md border bg-admin-canvas px-3"
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
                                        Por unidad o presentación ingresada
                                    </option>
                                </select>
                            </FormField>
                            <FormField
                                id={`price-${index}`}
                                label="Precio en pesos enteros"
                                error={errors[`lines.${index}.price_amount`]}
                            >
                                <Input
                                    id={`price-${index}`}
                                    inputMode="numeric"
                                    required
                                    value={line.price_amount}
                                    onChange={(e) =>
                                        update(index, {
                                            price_amount: e.target.value,
                                        })
                                    }
                                />
                            </FormField>
                            {errors[`lines.${index}`] && (
                                <p role="alert">{errors[`lines.${index}`]}</p>
                            )}
                            {form.data.lines.length > 1 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData(
                                            'lines',
                                            form.data.lines.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    Quitar línea {index + 1}
                                </Button>
                            )}
                        </fieldset>
                    );
                })}
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.data.lines.length >= 100}
                    onClick={() =>
                        form.setData('lines', [...form.data.lines, blank()])
                    }
                >
                    Agregar línea
                </Button>
                <FormField
                    id="shipping"
                    label="Envío en pesos enteros"
                    error={errors.shipping_amount}
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
                {Object.entries(errors)
                    .filter(
                        ([key]) =>
                            !key.startsWith('lines.') ||
                            key.endsWith('.price_amount'),
                    )
                    .map(([key, error]) => (
                        <p role="alert" key={key}>
                            {error}
                        </p>
                    ))}
                <Button
                    disabled={
                        form.processing ||
                        !wallets.length ||
                        !inventory_items.length
                    }
                >
                    Confirmar compra
                </Button>
            </form>
        </section>
    );
}
