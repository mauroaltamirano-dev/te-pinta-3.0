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
                                onChange={(address) =>
                                    form.setData((data) => ({
                                        ...data,
                                        customer: { ...data.customer, address },
                                        delivery_address:
                                            data.delivery_address || address,
                                    }))
                                }
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
                    <label className="flex items-center gap-3 rounded-lg bg-admin-surface-raised p-3">
                        <Checkbox
                            checked={form.data.payment_status === 'paid'}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'payment_status',
                                    checked === true ? 'paid' : 'unpaid',
                                )
                            }
                        />
                        <span>
                            {form.data.payment_status === 'paid'
                                ? 'Pagado'
                                : 'No pagado'}
                        </span>
                    </label>
                </section>
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
