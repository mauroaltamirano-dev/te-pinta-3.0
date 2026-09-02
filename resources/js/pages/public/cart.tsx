import { Head, Link } from '@inertiajs/react';
import { Minus, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { useCart } from '@/features/orders/cart-context';
import { PriceSummary } from '@/features/orders/components/price-summary';
import { postJson } from '@/features/orders/http';
import type {
    CreatedOrderResponse,
    DeliveryPeriod,
    DeliveryType,
    OrderAddon,
    OrderCondition,
    PriceQuote,
    PublicProduct,
} from '@/features/orders/types';

export default function Cart() {
    const cart = useCart();
    const [catalog, setCatalog] = useState<PublicProduct[]>([]);
    const [availableAddons, setAvailableAddons] = useState<OrderAddon[]>([]);
    const [condition, setCondition] = useState<OrderCondition>('cooked');
    const [deliveryType, setDeliveryType] = useState<DeliveryType>('pickup');
    const [deliveryPeriod, setDeliveryPeriod] =
        useState<DeliveryPeriod>('night');
    const [businessToday, setBusinessToday] = useState('');
    const [deliveryDate, setDeliveryDate] = useState('');
    const [deliveryTime, setDeliveryTime] = useState('');
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [address, setAddress] = useState('');
    const [notes, setNotes] = useState('');
    const [quote, setQuote] = useState<PriceQuote | null>(null);
    const [loadingQuote, setLoadingQuote] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => {
        Promise.all([
            fetch('/menu/catalog', {
                headers: { Accept: 'application/json' },
            }).then((response) => response.json()) as Promise<{
                data: PublicProduct[];
            }>,
            fetch('/checkout/options', {
                headers: { Accept: 'application/json' },
            }).then((response) => response.json()) as Promise<{
                data: { addons: OrderAddon[]; today: string };
            }>,
        ]).then(([catalogPayload, optionsPayload]) => {
            setCatalog(catalogPayload.data);
            setAvailableAddons(optionsPayload.data.addons);
            setBusinessToday(optionsPayload.data.today);
            setDeliveryDate(
                (currentDate) => currentDate || optionsPayload.data.today,
            );
        });
    }, []);
    const requestBody = useMemo(
        () => ({
            items: cart.items,
            addons: cart.addons,
            condition,
            delivery_type: deliveryType,
        }),
        [cart.items, cart.addons, condition, deliveryType],
    );
    useEffect(() => {
        if (!cart.items.length) {
            return;
        }

        const timer = window.setTimeout(() => {
            setLoadingQuote(true);
            postJson<{ data: PriceQuote }>(
                '/checkout/price-preview',
                requestBody,
            )
                .then(({ data }) => {
                    setQuote(data);
                    setError('');
                })
                .catch((reason: Error) => setError(reason.message))
                .finally(() => setLoadingQuote(false));
        }, 250);

        return () => window.clearTimeout(timer);
    }, [cart.items.length, requestBody]);
    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setSubmitting(true);
        setError('');

        try {
            const { data: result } = await postJson<{
                data: CreatedOrderResponse;
            }>('/checkout/orders', {
                ...requestBody,
                customer: {
                    name,
                    phone,
                    address: deliveryType === 'delivery' ? address : null,
                },
                delivery_date: deliveryDate,
                delivery_period: deliveryPeriod,
                delivery_time: deliveryTime || null,
                delivery_address: deliveryType === 'delivery' ? address : null,
                notes: notes || null,
                idempotency_key: cart.idempotency_key,
            });
            cart.clear();
            window.location.assign(result.whatsapp_url);
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'No se pudo crear el pedido.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <Head title="Carrito" />
            <section className="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:px-6 sm:py-12">
                <header>
                    <p className="text-xs font-semibold tracking-[0.18em] text-primary uppercase">
                        Finalizar compra
                    </p>
                    <h1 className="mt-1 font-display text-3xl font-bold">
                        Carrito
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        El pedido se guarda antes de abrir WhatsApp. El pago
                        queda pendiente.
                    </p>
                </header>
                {!cart.items.length ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <p className="text-muted-foreground">
                                Tu carrito está vacío.
                            </p>
                            <Button asChild className="mt-4">
                                <Link href="/menu">Ver menú</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <form
                        onSubmit={submit}
                        className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
                    >
                        <div className="grid gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Productos</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    {cart.items.map((item) => {
                                        const product = catalog.find(
                                            (entry) =>
                                                entry.slug ===
                                                item.product_slug,
                                        );

                                        return (
                                            <div
                                                key={item.product_slug}
                                                className="flex items-center justify-between gap-3 border-b pb-4"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        {product?.name ??
                                                            item.product_slug}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Te Pinta calcula la
                                                        combinación más
                                                        conveniente.
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label={`Quitar una unidad de ${product?.name ?? item.product_slug}`}
                                                        onClick={() =>
                                                            cart.setProductQuantity(
                                                                item.product_slug,
                                                                item.quantity -
                                                                    1,
                                                            )
                                                        }
                                                    >
                                                        <Minus />
                                                    </Button>
                                                    <output
                                                        aria-label="Cantidad"
                                                        className="w-8 text-center tabular-nums"
                                                    >
                                                        {item.quantity}
                                                    </output>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label={`Agregar una unidad de ${product?.name ?? item.product_slug}`}
                                                        onClick={() =>
                                                            cart.setProductQuantity(
                                                                item.product_slug,
                                                                item.quantity +
                                                                    1,
                                                            )
                                                        }
                                                    >
                                                        <Plus />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Quitar ${product?.name ?? item.product_slug} del carrito`}
                                                        onClick={() =>
                                                            cart.setProductQuantity(
                                                                item.product_slug,
                                                                0,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                    <fieldset className="grid gap-2">
                                        <legend className="text-sm font-medium">
                                            Preparación
                                        </legend>
                                        <label className="flex cursor-pointer items-start gap-3 rounded-lg border p-3">
                                            <Checkbox
                                                checked={condition === 'raw'}
                                                onCheckedChange={(checked) =>
                                                    setCondition(
                                                        checked === true
                                                            ? 'raw'
                                                            : 'cooked',
                                                    )
                                                }
                                            />
                                            <span>
                                                <span className="block text-sm font-medium">
                                                    Pedir crudas
                                                </span>
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    Sin seleccionar, las
                                                    preparamos cocinadas.
                                                </span>
                                            </span>
                                        </label>
                                    </fieldset>
                                    {availableAddons.length > 0 && (
                                        <fieldset className="grid gap-3">
                                            <legend className="text-sm font-medium">
                                                Adicionales
                                            </legend>
                                            {availableAddons.map((addon) => {
                                                const quantity =
                                                    cart.addons.find(
                                                        (entry) =>
                                                            entry.addon_slug ===
                                                            addon.slug,
                                                    )?.quantity ?? 0;

                                                return (
                                                    <div
                                                        key={addon.slug}
                                                        className="flex items-center justify-between"
                                                    >
                                                        <span>
                                                            {addon.name}
                                                        </span>
                                                        <div className="flex items-center gap-2">
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="outline"
                                                                aria-label={`Quitar un adicional ${addon.name}`}
                                                                onClick={() =>
                                                                    cart.setAddonQuantity(
                                                                        addon.slug,
                                                                        quantity -
                                                                            1,
                                                                    )
                                                                }
                                                            >
                                                                <Minus />
                                                            </Button>
                                                            <output>
                                                                {quantity}
                                                            </output>
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="outline"
                                                                aria-label={`Agregar un adicional ${addon.name}`}
                                                                onClick={() =>
                                                                    cart.setAddonQuantity(
                                                                        addon.slug,
                                                                        quantity +
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
                                    )}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Tus datos</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <Field
                                        id="checkout-name"
                                        label="Nombre"
                                        value={name}
                                        onChange={setName}
                                        required
                                    />
                                    <Field
                                        id="checkout-phone"
                                        label="Teléfono"
                                        value={phone}
                                        onChange={setPhone}
                                        required
                                        inputMode="tel"
                                    />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Entrega</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant={
                                                deliveryType === 'pickup'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            aria-pressed={
                                                deliveryType === 'pickup'
                                            }
                                            onClick={() =>
                                                setDeliveryType('pickup')
                                            }
                                        >
                                            Retiro
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={
                                                deliveryType === 'delivery'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            aria-pressed={
                                                deliveryType === 'delivery'
                                            }
                                            onClick={() =>
                                                setDeliveryType('delivery')
                                            }
                                        >
                                            Envío
                                        </Button>
                                    </div>
                                    {deliveryType === 'delivery' && (
                                        <Field
                                            id="checkout-address"
                                            label="Dirección de entrega"
                                            value={address}
                                            onChange={setAddress}
                                            required
                                        />
                                    )}
                                    <Field
                                        id="delivery-date"
                                        type="date"
                                        label="Fecha de entrega"
                                        value={deliveryDate}
                                        onChange={setDeliveryDate}
                                        required
                                        min={businessToday || undefined}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="delivery-period">
                                            Franja
                                        </Label>
                                        <Select
                                            value={deliveryPeriod}
                                            onValueChange={(value) =>
                                                setDeliveryPeriod(
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
                                        value={deliveryTime}
                                        onChange={setDeliveryTime}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="order-notes">
                                            Notas del pedido
                                        </Label>
                                        <Textarea
                                            id="order-notes"
                                            value={notes}
                                            onChange={(event) =>
                                                setNotes(event.target.value)
                                            }
                                            rows={3}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <aside className="grid content-start gap-4">
                            <PriceSummary
                                quote={quote}
                                loading={loadingQuote}
                            />
                            {error && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            )}
                            <Button
                                type="submit"
                                size="lg"
                                disabled={
                                    submitting || loadingQuote || !businessToday
                                }
                            >
                                {submitting
                                    ? 'Creando pedido…'
                                    : 'Confirmar y abrir WhatsApp'}
                            </Button>
                        </aside>
                    </form>
                )}
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
    required?: boolean;
    inputMode?: 'tel';
    min?: string;
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
