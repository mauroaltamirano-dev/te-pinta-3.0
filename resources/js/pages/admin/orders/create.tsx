import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Minus, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { PriceSummary } from '@/features/orders/components/price-summary';
import { postJson } from '@/features/orders/http';
import type {
    DeliveryPeriod,
    DeliveryType,
    OrderCondition,
    PaymentStatus,
    PriceQuote,
} from '@/features/orders/types';

interface ProductOption {
    id: number;
    name: string;
    unit_price: number;
    half_dozen_price: number;
    dozen_price: number;
}
interface CustomerOption {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
}
interface AddonOption {
    id: number;
    name: string;
    price: number;
}
interface AdminOrderForm {
    items: Array<{ product_id: number; quantity: number }>;
    addons: Array<{ addon_id: number; quantity: number }>;
    condition: OrderCondition;
    customer_id: number | null;
    customer: { name: string; phone: string; address: string };
    delivery_type: DeliveryType;
    delivery_date: string;
    delivery_period: DeliveryPeriod;
    delivery_time: string;
    delivery_address: string;
    notes: string;
    payment_status: PaymentStatus;
}
const today = new Date().toISOString().slice(0, 10);
export default function CreateOrder({
    products,
    customers,
    addons,
}: {
    products: ProductOption[];
    customers: CustomerOption[];
    addons: AddonOption[];
}) {
    const form = useForm<AdminOrderForm>({
        items: [],
        addons: [],
        condition: 'raw',
        customer_id: null,
        customer: { name: '', phone: '', address: '' },
        delivery_type: 'pickup',
        delivery_date: today,
        delivery_period: 'night',
        delivery_time: '',
        delivery_address: '',
        notes: '',
        payment_status: 'unpaid',
    });
    const [quote, setQuote] = useState<PriceQuote | null>(null);
    const [quoteLoading, setQuoteLoading] = useState(false);
    const [quoteError, setQuoteError] = useState('');
    const preview = useMemo(
        () => ({
            items: form.data.items,
            addons: form.data.addons,
            condition: form.data.condition,
            delivery_type: form.data.delivery_type,
        }),
        [
            form.data.items,
            form.data.addons,
            form.data.condition,
            form.data.delivery_type,
        ],
    );
    useEffect(() => {
        if (!preview.items.length) {
            return;
        }

        const timer = window.setTimeout(() => {
            setQuoteLoading(true);
            postJson<{ data: PriceQuote }>(
                '/admin/orders/price-preview',
                preview,
            )
                .then(({ data }) => {
                    setQuote(data);
                    setQuoteError('');
                })
                .catch((error: Error) => setQuoteError(error.message))
                .finally(() => setQuoteLoading(false));
        }, 250);

        return () => window.clearTimeout(timer);
    }, [preview]);
    const changeProduct = (id: number, increment: number) => {
        const found = form.data.items.find((item) => item.product_id === id);
        const quantity = (found?.quantity ?? 0) + increment;
        form.setData(
            'items',
            quantity <= 0
                ? form.data.items.filter((item) => item.product_id !== id)
                : found
                  ? form.data.items.map((item) =>
                        item.product_id === id ? { ...item, quantity } : item,
                    )
                  : [...form.data.items, { product_id: id, quantity }],
        );
    };
    const changeAddon = (id: number, increment: number) => {
        const found = form.data.addons.find((item) => item.addon_id === id);
        const quantity = (found?.quantity ?? 0) + increment;
        form.setData(
            'addons',
            quantity <= 0
                ? form.data.addons.filter((item) => item.addon_id !== id)
                : found
                  ? form.data.addons.map((item) =>
                        item.addon_id === id ? { ...item, quantity } : item,
                    )
                  : [...form.data.addons, { addon_id: id, quantity }],
        );
    };
    const selectCustomer = (value: string) => {
        if (value === 'new') {
            form.setData((data) => ({
                ...data,
                customer_id: null,
                customer: { name: '', phone: '', address: '' },
                delivery_address: '',
            }));

            return;
        }

        const customer = customers.find((item) => item.id === Number(value));

        if (customer) {
            form.setData((data) => ({
                ...data,
                customer_id: customer.id,
                customer: { name: '', phone: '', address: '' },
                delivery_address: customer.address ?? '',
            }));
        }
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/admin/orders');
    };

    return (
        <>
            <Head title="Nuevo pedido" />
            <section className="grid gap-6 font-orders">
                <header>
                    <Link
                        href="/admin/orders"
                        className="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <ArrowLeft className="size-4" /> Pedidos
                    </Link>
                    <h1 className="text-2xl font-bold">Crear pedido</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Productos → Cliente → Entrega → Resumen. El precio final
                        siempre lo determina el sistema.
                    </p>
                </header>
                <form
                    onSubmit={submit}
                    className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]"
                >
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>1. Productos</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        aria-pressed={
                                            form.data.condition === 'raw'
                                        }
                                        variant={
                                            form.data.condition === 'raw'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() =>
                                            form.setData('condition', 'raw')
                                        }
                                    >
                                        Crudo
                                    </Button>
                                    <Button
                                        type="button"
                                        aria-pressed={
                                            form.data.condition === 'cooked'
                                        }
                                        variant={
                                            form.data.condition === 'cooked'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() =>
                                            form.setData('condition', 'cooked')
                                        }
                                    >
                                        Cocinado
                                    </Button>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {products.map((product) => {
                                        const quantity =
                                            form.data.items.find(
                                                (item) =>
                                                    item.product_id ===
                                                    product.id,
                                            )?.quantity ?? 0;

                                        return (
                                            <div
                                                key={product.id}
                                                className="grid gap-3 rounded-lg border p-3"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        {product.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Cantidad: {quantity}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        aria-label={`Agregar una unidad de ${product.name}`}
                                                        onClick={() =>
                                                            changeProduct(
                                                                product.id,
                                                                1,
                                                            )
                                                        }
                                                    >
                                                        <Plus /> Unidad
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        aria-label={`Agregar media docena de ${product.name}`}
                                                        onClick={() =>
                                                            changeProduct(
                                                                product.id,
                                                                6,
                                                            )
                                                        }
                                                    >
                                                        +6
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        aria-label={`Agregar una docena de ${product.name}`}
                                                        onClick={() =>
                                                            changeProduct(
                                                                product.id,
                                                                12,
                                                            )
                                                        }
                                                    >
                                                        +12
                                                    </Button>
                                                    {quantity > 0 && (
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            aria-label={`Quitar una unidad de ${product.name}`}
                                                            onClick={() =>
                                                                changeProduct(
                                                                    product.id,
                                                                    -1,
                                                                )
                                                            }
                                                        >
                                                            <Minus />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                                <fieldset className="grid gap-3">
                                    <legend className="font-medium">
                                        Adicionales
                                    </legend>
                                    {addons.map((addon) => {
                                        const quantity =
                                            form.data.addons.find(
                                                (item) =>
                                                    item.addon_id === addon.id,
                                            )?.quantity ?? 0;

                                        return (
                                            <div
                                                key={addon.id}
                                                className="flex items-center justify-between"
                                            >
                                                <span>{addon.name}</span>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label={`Quitar un adicional ${addon.name}`}
                                                        onClick={() =>
                                                            changeAddon(
                                                                addon.id,
                                                                -1,
                                                            )
                                                        }
                                                    >
                                                        <Minus />
                                                    </Button>
                                                    <output>{quantity}</output>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label={`Agregar un adicional ${addon.name}`}
                                                        onClick={() =>
                                                            changeAddon(
                                                                addon.id,
                                                                1,
                                                            )
                                                        }
                                                    >
                                                        <Plus />
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </fieldset>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>2. Cliente</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="customer-selection">
                                        Cliente
                                    </Label>
                                    <Select
                                        value={
                                            form.data.customer_id?.toString() ??
                                            'new'
                                        }
                                        onValueChange={selectCustomer}
                                    >
                                        <SelectTrigger id="customer-selection">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="new">
                                                Crear cliente rápido
                                            </SelectItem>
                                            {customers.map((customer) => (
                                                <SelectItem
                                                    key={customer.id}
                                                    value={customer.id.toString()}
                                                >
                                                    {customer.name}
                                                    {customer.phone
                                                        ? ` · ${customer.phone}`
                                                        : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                {form.data.customer_id === null && (
                                    <>
                                        <Field
                                            id="new-customer-name"
                                            label="Nombre"
                                            value={form.data.customer.name}
                                            onChange={(value) =>
                                                form.setData('customer', {
                                                    ...form.data.customer,
                                                    name: value,
                                                })
                                            }
                                            required
                                        />
                                        <Field
                                            id="new-customer-phone"
                                            label="Teléfono"
                                            value={form.data.customer.phone}
                                            onChange={(value) =>
                                                form.setData('customer', {
                                                    ...form.data.customer,
                                                    phone: value,
                                                })
                                            }
                                        />
                                        <Field
                                            id="new-customer-address"
                                            label="Dirección"
                                            value={form.data.customer.address}
                                            onChange={(value) => {
                                                form.setData((data) => ({
                                                    ...data,
                                                    customer: {
                                                        ...data.customer,
                                                        address: value,
                                                    },
                                                    delivery_address:
                                                        data.delivery_address ||
                                                        value,
                                                }));
                                            }}
                                        />
                                    </>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>3. Entrega</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant={
                                            form.data.delivery_type === 'pickup'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'delivery_type',
                                                'pickup',
                                            )
                                        }
                                    >
                                        Retiro
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            form.data.delivery_type ===
                                            'delivery'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'delivery_type',
                                                'delivery',
                                            )
                                        }
                                    >
                                        Envío
                                    </Button>
                                </div>
                                {form.data.delivery_type === 'delivery' && (
                                    <Field
                                        id="delivery-address"
                                        label="Dirección de entrega"
                                        value={form.data.delivery_address}
                                        onChange={(value) =>
                                            form.setData(
                                                'delivery_address',
                                                value,
                                            )
                                        }
                                        required
                                    />
                                )}
                                <Field
                                    id="delivery-date"
                                    type="date"
                                    min={today}
                                    label="Fecha de entrega"
                                    value={form.data.delivery_date}
                                    onChange={(value) =>
                                        form.setData('delivery_date', value)
                                    }
                                    required
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="delivery-period">
                                        Franja horaria
                                    </Label>
                                    <Select
                                        value={form.data.delivery_period}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'delivery_period',
                                                value as DeliveryPeriod,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="delivery-period">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="midday">
                                                Mediodía
                                            </SelectItem>
                                            <SelectItem value="afternoon">
                                                Tarde
                                            </SelectItem>
                                            <SelectItem value="night">
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
                                <div className="grid gap-2">
                                    <Label htmlFor="order-notes">
                                        Notas del pedido
                                    </Label>
                                    <Textarea
                                        id="order-notes"
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                    <aside className="grid content-start gap-4">
                        <h2 className="text-lg font-semibold">4. Resumen</h2>
                        <PriceSummary
                            quote={form.data.items.length ? quote : null}
                            loading={quoteLoading}
                        />
                        {quoteError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                No pudimos calcular el precio. Revisá los
                                productos e intentá nuevamente.
                            </p>
                        )}
                        <label className="flex items-center gap-2 rounded-lg border p-3">
                            <Checkbox
                                checked={form.data.payment_status === 'paid'}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'payment_status',
                                        checked === true ? 'paid' : 'unpaid',
                                    )
                                }
                            />
                            Pagado
                        </label>
                        {Object.keys(form.errors).length > 0 && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                Revisá los datos del pedido e intentá
                                nuevamente.
                            </p>
                        )}
                        <Button
                            type="submit"
                            size="lg"
                            disabled={form.processing || quoteLoading || !quote}
                        >
                            Crear pedido
                        </Button>
                    </aside>
                </form>
            </section>
        </>
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
