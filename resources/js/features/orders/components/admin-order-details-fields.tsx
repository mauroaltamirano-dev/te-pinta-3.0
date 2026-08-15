import { Minus, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    AdminAddonOption,
    AdminCustomerOption,
} from '../admin-order-types';
import type { AdminOrderDraft } from '../hooks/use-admin-order-draft';

export function AdminOrderDetailsFields({
    draft,
    customers,
    addons,
    minimumDeliveryDate,
}: {
    draft: AdminOrderDraft;
    customers: AdminCustomerOption[];
    addons: AdminAddonOption[];
    minimumDeliveryDate?: string | null;
}) {
    const { form } = draft;
    const [customerSearch, setCustomerSearch] = useState('');
    const filteredCustomers = useMemo(() => {
        const term = customerSearch.trim().toLocaleLowerCase('es');

        if (!term) {
            return customers;
        }

        return customers.filter((customer) =>
            `${customer.name} ${customer.phone ?? ''}`
                .toLocaleLowerCase('es')
                .includes(term),
        );
    }, [customerSearch, customers]);
    const selectedCustomer = customers.find(
        (customer) => customer.id === form.data.customer_id,
    );
    const noPaymentRequired = draft.quote?.total === 0;

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <div className="grid content-start gap-5">
                <section className="grid gap-3">
                    <h3 className="font-semibold">Cliente</h3>
                    <Field
                        id="customer-search"
                        label="Buscar cliente"
                        value={customerSearch}
                        onChange={setCustomerSearch}
                        placeholder="Nombre o teléfono"
                    />
                    {customerSearch.trim() && (
                        <div className="grid gap-2">
                            <p className="sr-only" role="status">
                                {filteredCustomers.length === 1
                                    ? '1 coincidencia encontrada'
                                    : `${filteredCustomers.length} coincidencias encontradas`}
                            </p>
                            {filteredCustomers.length > 0 ? (
                                <ul
                                    className="grid gap-1"
                                    aria-label="Coincidencias de clientes"
                                >
                                    {filteredCustomers.map((customer) => (
                                        <li key={customer.id}>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    draft.selectCustomer(
                                                        customer.id.toString(),
                                                    );
                                                    setCustomerSearch('');
                                                }}
                                                className="w-full rounded-lg bg-admin-surface-raised px-3 py-2 text-left text-sm transition-colors hover:bg-admin-accent/10 focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none"
                                            >
                                                <span className="font-medium">
                                                    {customer.name}
                                                </span>
                                                {customer.phone && (
                                                    <span className="text-admin-text-secondary">
                                                        {' · '}
                                                        {customer.phone}
                                                    </span>
                                                )}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-admin-text-secondary">
                                    No encontramos clientes.
                                </p>
                            )}
                        </div>
                    )}
                    {selectedCustomer && (
                        <div className="flex items-center justify-between gap-3 rounded-lg bg-admin-surface-raised px-3 py-2 text-sm">
                            <span>
                                <span className="font-medium">
                                    {selectedCustomer.name}
                                </span>
                                {selectedCustomer.phone
                                    ? ` · ${selectedCustomer.phone}`
                                    : ''}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => draft.selectCustomer('new')}
                            >
                                Crear cliente rápido
                            </Button>
                        </div>
                    )}
                    {form.data.customer_id === null && (
                        <>
                            <Field
                                id="new-customer-name"
                                label="Nombre"
                                value={form.data.customer.name}
                                onChange={(name) =>
                                    form.setData('customer', {
                                        ...form.data.customer,
                                        name,
                                    })
                                }
                                required
                            />
                            <Field
                                id="new-customer-phone"
                                label="Teléfono"
                                value={form.data.customer.phone}
                                onChange={(phone) =>
                                    form.setData('customer', {
                                        ...form.data.customer,
                                        phone,
                                    })
                                }
                            />
                            <Field
                                id="new-customer-address"
                                label="Dirección"
                                value={form.data.customer.address}
                                onChange={draft.setCustomerAddress}
                            />
                        </>
                    )}
                </section>

                <section className="grid gap-3">
                    <h3 className="font-semibold">Condición</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {(['raw', 'cooked'] as const).map((condition) => (
                            <Button
                                key={condition}
                                type="button"
                                aria-pressed={form.data.condition === condition}
                                variant={
                                    form.data.condition === condition
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    form.setData('condition', condition)
                                }
                            >
                                {condition === 'raw' ? 'Crudo' : 'Cocinado'}
                            </Button>
                        ))}
                    </div>
                </section>

                <fieldset className="grid gap-3">
                    <legend className="mb-2 font-semibold">Adicionales</legend>
                    {addons.map((addon) => {
                        const quantity =
                            form.data.addons.find(
                                (line) => line.addon_id === addon.id,
                            )?.quantity ?? 0;

                        return (
                            <div
                                key={addon.id}
                                className="flex items-center justify-between gap-3 rounded-lg bg-admin-surface-raised p-3"
                            >
                                <span>
                                    {addon.name}
                                    {addon.is_sellable === false && (
                                        <span className="mt-1 block text-xs font-medium text-amber-400">
                                            Adicional actualmente no disponible
                                        </span>
                                    )}
                                </span>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        aria-label={`Quitar un adicional ${addon.name}`}
                                        onClick={() =>
                                            draft.changeAddon(addon.id, -1)
                                        }
                                        disabled={
                                            addon.is_sellable === false &&
                                            quantity <= 0
                                        }
                                    >
                                        <Minus />
                                    </Button>
                                    <output className="min-w-5 text-center tabular-nums">
                                        {quantity}
                                    </output>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        aria-label={`Agregar un adicional ${addon.name}`}
                                        onClick={() =>
                                            draft.changeAddon(addon.id, 1)
                                        }
                                        disabled={addon.is_sellable === false}
                                    >
                                        <Plus />
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </fieldset>
            </div>

            <div className="grid content-start gap-5">
                <section className="grid gap-3">
                    <h3 className="font-semibold">Entrega</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {(['pickup', 'delivery'] as const).map((type) => (
                            <Button
                                key={type}
                                type="button"
                                variant={
                                    form.data.delivery_type === type
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => draft.selectDeliveryType(type)}
                            >
                                {type === 'pickup'
                                    ? 'Retiro en local'
                                    : 'Envío'}
                            </Button>
                        ))}
                    </div>
                    {form.data.delivery_type === 'delivery' && (
                        <Field
                            id="delivery-address"
                            label="Dirección de entrega"
                            value={form.data.delivery_address}
                            onChange={draft.setDeliveryAddress}
                            required
                        />
                    )}
                    <Field
                        id="delivery-date"
                        type="date"
                        min={
                            minimumDeliveryDate === undefined
                                ? draft.today
                                : (minimumDeliveryDate ?? undefined)
                        }
                        label="Fecha"
                        value={form.data.delivery_date}
                        onChange={(value) =>
                            form.setData('delivery_date', value)
                        }
                        required
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="delivery-period">Franja</Label>
                        <Select
                            value={form.data.delivery_period}
                            onValueChange={(
                                value: 'midday' | 'afternoon' | 'night',
                            ) => form.setData('delivery_period', value)}
                        >
                            <SelectTrigger id="delivery-period">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="midday">Mediodía</SelectItem>
                                <SelectItem value="afternoon">Tarde</SelectItem>
                                <SelectItem value="night">Noche</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Field
                        id="delivery-time"
                        type="time"
                        label="Hora opcional"
                        value={form.data.delivery_time}
                        onChange={(value) =>
                            form.setData('delivery_time', value)
                        }
                    />
                </section>

                <section className="grid gap-3">
                    <Label htmlFor="order-notes">Notas del pedido</Label>
                    <Textarea
                        id="order-notes"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        placeholder="Opcional"
                    />
                </section>

                <fieldset className="grid gap-3">
                    <legend className="font-semibold">Descuento</legend>
                    <label className="flex items-center gap-3 rounded-lg bg-admin-surface-raised p-3">
                        <Checkbox
                            checked={form.data.discount_type !== null}
                            onCheckedChange={(checked) => {
                                form.setData((data) => ({
                                    ...data,
                                    discount_type:
                                        checked === true
                                            ? (data.discount_type ??
                                              'percentage')
                                            : null,
                                    discount_value:
                                        checked === true
                                            ? (data.discount_value ?? 0)
                                            : null,
                                }));
                            }}
                        />
                        <span>Aplicar descuento</span>
                    </label>
                    {form.data.discount_type !== null && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="discount-type">
                                    Tipo de descuento
                                </Label>
                                <Select
                                    value={form.data.discount_type}
                                    onValueChange={(
                                        discountType:
                                            'percentage' | 'fixed_amount',
                                    ) =>
                                        form.setData((data) => ({
                                            ...data,
                                            discount_type: discountType,
                                            discount_value: 0,
                                        }))
                                    }
                                >
                                    <SelectTrigger id="discount-type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="percentage">
                                            Porcentaje
                                        </SelectItem>
                                        <SelectItem value="fixed_amount">
                                            Monto fijo
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="discount-value">
                                    {form.data.discount_type === 'percentage'
                                        ? 'Porcentaje'
                                        : 'Monto en pesos'}
                                </Label>
                                <Input
                                    id="discount-value"
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    max={
                                        form.data.discount_type === 'percentage'
                                            ? 100
                                            : undefined
                                    }
                                    step={1}
                                    aria-describedby="discount-help"
                                    value={form.data.discount_value ?? ''}
                                    onChange={(event) => {
                                        const value = event.target.value;
                                        form.setData(
                                            'discount_value',
                                            value === ''
                                                ? null
                                                : Number.parseInt(value, 10),
                                        );
                                    }}
                                    required
                                />
                            </div>
                            <p
                                id="discount-help"
                                className="text-xs text-admin-text-secondary sm:col-span-2"
                            >
                                Se aplica a productos, cocción y adicionales. El
                                envío no se descuenta.
                            </p>
                        </div>
                    )}
                </fieldset>

                <fieldset className="grid gap-3">
                    <legend className="font-semibold">Pago</legend>
                    <label className="flex items-center gap-3 rounded-lg bg-admin-surface-raised p-3">
                        <Checkbox
                            checked={form.data.payment_status === 'paid'}
                            disabled={noPaymentRequired}
                            onCheckedChange={(checked) => {
                                if (checked !== true) {
                                    form.clearErrors('payment_method');
                                }

                                form.setData((data) => ({
                                    ...data,
                                    payment_status:
                                        checked === true ? 'paid' : 'unpaid',
                                    payment_method:
                                        checked === true
                                            ? data.payment_method
                                            : null,
                                }));
                            }}
                        />
                        <span>
                            {noPaymentRequired
                                ? 'No requiere pago — total $0'
                                : form.data.payment_status === 'paid'
                                  ? 'Pagado'
                                  : 'No pagado'}
                        </span>
                    </label>
                    {!noPaymentRequired &&
                        form.data.payment_status === 'paid' && (
                            <div className="grid gap-2">
                                <Label htmlFor="payment-method">
                                    Medio de pago
                                </Label>
                                <Select
                                    value={form.data.payment_method ?? ''}
                                    onValueChange={(
                                        paymentMethod:
                                            | 'cash'
                                            | 'bank_transfer'
                                            | 'debit_card',
                                    ) => {
                                        form.clearErrors('payment_method');
                                        form.setData(
                                            'payment_method',
                                            paymentMethod,
                                        );
                                    }}
                                    required
                                >
                                    <SelectTrigger
                                        id="payment-method"
                                        aria-describedby="payment-method-help"
                                        aria-invalid={
                                            form.errors.payment_method
                                                ? true
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Seleccioná un medio" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cash">
                                            Efectivo
                                        </SelectItem>
                                        <SelectItem value="bank_transfer">
                                            Transferencia
                                        </SelectItem>
                                        <SelectItem value="debit_card">
                                            Débito
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    id="payment-method-help"
                                    className={
                                        form.errors.payment_method
                                            ? 'text-sm text-destructive'
                                            : 'text-xs text-admin-text-secondary'
                                    }
                                >
                                    {form.errors.payment_method ??
                                        'Es obligatorio para marcar el pedido como pagado.'}
                                </p>
                            </div>
                        )}
                </fieldset>
            </div>
        </div>
    );
}

function Field({
    id,
    label,
    value,
    onChange,
    ...props
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    min?: string;
    required?: boolean;
    placeholder?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                {...props}
            />
        </div>
    );
}
