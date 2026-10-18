import { es } from 'date-fns/locale';
import { CalendarIcon, Minus, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
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
import { formatOrderDate } from '../presentation';

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
                                required={form.data.delivery_type !== 'dine_in'}
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
                            {form.data.delivery_type !== 'dine_in' && (
                                <Field
                                    id="new-customer-address"
                                    label="Dirección"
                                    value={form.data.customer.address}
                                    onChange={draft.setCustomerAddress}
                                />
                            )}
                        </>
                    )}
                </section>

                <section className="grid gap-3">
                    <h3 className="font-semibold">Preparación</h3>
                    <label className="flex cursor-pointer items-start gap-3 rounded-lg bg-admin-surface-raised p-3">
                        <Checkbox
                            checked={form.data.condition === 'raw'}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'condition',
                                    checked === true ? 'raw' : 'cooked',
                                )
                            }
                        />
                        <span>
                            <span className="block text-sm font-medium">
                                Entregar crudas
                            </span>
                            <span className="mt-1 block text-xs text-admin-text-secondary">
                                Sin seleccionar, el pedido se prepara cocinado.
                            </span>
                        </span>
                    </label>
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
                                        <span className="mt-1 block text-xs font-medium text-warning">
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
                    {form.data.delivery_type === 'dine_in' ? (
                        <p>
                            Salón · pedido inmediato, sin dirección ni
                            programación.
                        </p>
                    ) : (
                        <>
                            <div className="grid grid-cols-2 gap-3">
                                {(['pickup', 'delivery'] as const).map(
                                    (type) => (
                                        <Button
                                            key={type}
                                            type="button"
                                            variant={
                                                form.data.delivery_type === type
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                draft.selectDeliveryType(type)
                                            }
                                        >
                                            {type === 'pickup'
                                                ? 'Retiro en local'
                                                : 'Envío'}
                                        </Button>
                                    ),
                                )}
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
                            <DeliveryDatePicker
                                value={form.data.delivery_date}
                                minimumDate={minimumDeliveryDate}
                                onChange={(value) =>
                                    form.setData('delivery_date', value)
                                }
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="delivery-period">Franja</Label>
                                <Select
                                    value={form.data.delivery_period ?? ''}
                                    onValueChange={(
                                        value: 'midday' | 'afternoon' | 'night',
                                    ) => form.setData('delivery_period', value)}
                                >
                                    <SelectTrigger
                                        id="delivery-period"
                                        className="h-11 w-full border-admin-border !bg-admin-canvas text-admin-text-primary focus-visible:border-admin-accent focus-visible:ring-admin-accent/30"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="admin-shell border-admin-border !bg-admin-surface-raised !text-admin-text-primary shadow-xl">
                                        <SelectItem
                                            value="midday"
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
                                            Mediodía
                                        </SelectItem>
                                        <SelectItem
                                            value="afternoon"
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
                                            Tarde
                                        </SelectItem>
                                        <SelectItem
                                            value="night"
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
                                            Noche
                                        </SelectItem>
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
                        </>
                    )}
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
                                    <SelectTrigger
                                        id="discount-type"
                                        className="h-11 w-full border-admin-border !bg-admin-canvas text-admin-text-primary focus-visible:border-admin-accent focus-visible:ring-admin-accent/30"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent className="admin-shell border-admin-border !bg-admin-surface-raised !text-admin-text-primary shadow-xl">
                                        <SelectItem
                                            value="percentage"
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
                                            Porcentaje
                                        </SelectItem>
                                        <SelectItem
                                            value="fixed_amount"
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
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
                                Se aplica a productos y adicionales. El envío no
                                se descuenta.
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
                                        className="h-11 w-full border-admin-border !bg-admin-canvas text-admin-text-primary focus-visible:border-admin-accent focus-visible:ring-admin-accent/30"
                                        aria-invalid={
                                            form.errors.payment_method
                                                ? true
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Seleccioná un medio" />
                                    </SelectTrigger>
                                    <SelectContent className="admin-shell border-admin-border !bg-admin-surface-raised !text-admin-text-primary shadow-xl">
                                        <SelectItem
                                            value="cash"
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
                                            Efectivo
                                        </SelectItem>
                                        <SelectItem
                                            value="bank_transfer"
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
                                            Transferencia
                                        </SelectItem>
                                        <SelectItem
                                            value="debit_card"
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
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

function DeliveryDatePicker({
    value,
    minimumDate,
    onChange,
}: {
    value: string;
    minimumDate?: string | null;
    onChange: (value: string) => void;
}) {
    const selectedDate = value ? parseDate(value) : undefined;

    return (
        <div className="grid gap-2">
            <Label>Fecha</Label>

            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        className="justify-start border-admin-border !bg-admin-canvas text-left font-normal text-admin-text-primary hover:!bg-admin-selection hover:text-admin-text-primary focus-visible:border-admin-accent focus-visible:ring-admin-accent/30"
                    >
                        <CalendarIcon className="size-4" />

                        {selectedDate
                            ? formatOrderDate(value)
                            : 'Seleccionar fecha'}
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="admin-shell w-auto border-admin-border !bg-admin-surface-raised p-0 !text-admin-text-primary shadow-xl"
                    align="start"
                >
                    <Calendar
                        mode="single"
                        locale={es}
                        selected={selectedDate}
                        className="!bg-admin-surface-raised !text-admin-text-primary"
                        classNames={{
                            weekday:
                                'flex-1 rounded-md text-[0.8rem] font-normal text-admin-text-secondary select-none',
                            outside:
                                'text-admin-text-tertiary aria-selected:text-admin-text-tertiary',
                            disabled: 'text-admin-text-tertiary opacity-40',
                            today: 'rounded-md bg-admin-selection text-admin-accent-text data-[selected=true]:bg-admin-accent data-[selected=true]:text-admin-on-accent',
                        }}
                        disabled={
                            minimumDate
                                ? { before: parseDate(minimumDate) }
                                : undefined
                        }
                        onSelect={(date) => {
                            if (!date) {
                                return;
                            }

                            onChange(formatDateValue(date));
                        }}
                        autoFocus
                    />
                </PopoverContent>
            </Popover>
        </div>
    );
}

function parseDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function formatDateValue(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
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
