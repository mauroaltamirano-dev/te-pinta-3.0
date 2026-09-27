import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Minus, Plus, Trash2 } from 'lucide-react';
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
    const [quoteResult, setQuoteResult] = useState<{
        key: string;
        data: PriceQuote;
    } | null>(null);
    const [loadingOptions, setLoadingOptions] = useState(true);
    const [optionsError, setOptionsError] = useState('');
    const [reloadOptions, setReloadOptions] = useState(0);
    const [quoteFailure, setQuoteFailure] = useState<{
        key: string;
        message: string;
    } | null>(null);
    const [quoteRetry, setQuoteRetry] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => {
        let active = true;
        Promise.all([
            fetch('/menu/catalog', {
                headers: { Accept: 'application/json' },
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('catalog');
                }

                return response.json();
            }) as Promise<{
                data: PublicProduct[];
            }>,
            fetch('/checkout/options', {
                headers: { Accept: 'application/json' },
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('options');
                }

                return response.json();
            }) as Promise<{
                data: { addons: OrderAddon[]; today: string };
            }>,
        ])
            .then(([catalogPayload, optionsPayload]) => {
                if (!active) {
                    return;
                }

                setCatalog(catalogPayload.data);
                setAvailableAddons(optionsPayload.data.addons);
                setBusinessToday(optionsPayload.data.today);
                setDeliveryDate(
                    (currentDate) => currentDate || optionsPayload.data.today,
                );
            })
            .catch(() => {
                if (active) {
                    setOptionsError(
                        'No pudimos cargar el menú y las opciones de entrega. Intentá nuevamente.',
                    );
                }
            })
            .finally(() => {
                if (active) {
                    setLoadingOptions(false);
                }
            });

        return () => {
            active = false;
        };
    }, [reloadOptions]);
    const requestBody = useMemo(
        () => ({
            items: cart.items,
            addons: cart.addons,
            condition,
            delivery_type: deliveryType,
        }),
        [cart.items, cart.addons, condition, deliveryType],
    );
    const quoteKey = `${JSON.stringify(requestBody)}:${quoteRetry}`;
    const quote = quoteResult?.key === quoteKey ? quoteResult.data : null;
    const quoteError =
        quoteFailure?.key === quoteKey ? quoteFailure.message : '';
    const loadingQuote = cart.items.length > 0 && !quote && !quoteError;
    useEffect(() => {
        let active = true;

        if (!cart.items.length) {
            return;
        }

        const timer = window.setTimeout(() => {
            postJson<{ data: PriceQuote }>(
                '/checkout/price-preview',
                requestBody,
            )
                .then(({ data }) => {
                    if (!active) {
                        return;
                    }

                    setQuoteResult({ key: quoteKey, data });
                })
                .catch((reason: Error) => {
                    if (active) {
                        setQuoteFailure({
                            key: quoteKey,
                            message: reason.message,
                        });
                    }
                });
        }, 250);

        return () => {
            active = false;
            window.clearTimeout(timer);
        };
    }, [cart.items.length, requestBody, quoteKey]);
    const missingProducts =
        !loadingOptions && !optionsError
            ? cart.items.filter(
                  (item) =>
                      !catalog.some(
                          (entry) => entry.slug === item.product_slug,
                      ),
              )
            : [];
    const missingAddons =
        !loadingOptions && !optionsError
            ? cart.addons.filter(
                  (item) =>
                      !availableAddons.some(
                          (entry) => entry.slug === item.addon_slug,
                      ),
              )
            : [];
    const canSubmit =
        !submitting &&
        !loadingOptions &&
        !optionsError &&
        !loadingQuote &&
        !quoteError &&
        missingProducts.length === 0 &&
        missingAddons.length === 0 &&
        Boolean(quote) &&
        Boolean(businessToday);
    const submit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

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
                    : 'No se pudo completar la solicitud. Verificá el pedido antes de volver a intentarlo.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <Head title="Carrito" />
            <section className="mx-auto max-w-7xl px-4 pt-8 pb-32 sm:px-8 sm:pt-12 sm:pb-20 lg:px-12">
                <header className="mb-8 border-b border-border pb-8 sm:mb-10 sm:pb-10">
                    <Link
                        href="/menu"
                        className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-public-earth-ink underline-offset-4 hover:underline"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />{' '}
                        Volver al menú
                    </Link>
                    <p className="mt-5 text-xs font-bold tracking-[0.2em] text-public-earth-ink uppercase">
                        Tu pedido · Paso final
                    </p>
                    <h1 className="mt-3 font-display text-5xl leading-none font-black tracking-tight sm:text-6xl">
                        Tu carrito<span className="text-primary">.</span>
                    </h1>
                    <p className="mt-4 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                        Revisá lo que elegiste, completá tus datos y abrí
                        WhatsApp para enviar el mensaje. El pedido queda
                        pendiente de confirmación.
                    </p>
                </header>
                {!cart.items.length ? (
                    <div className="mx-auto max-w-xl rounded-[2rem] border border-border bg-card px-6 py-16 text-center sm:px-10">
                        <h2 className="font-display text-3xl font-bold">
                            Todavía no elegiste nada.
                        </h2>
                        <p className="mt-3 text-sm leading-6 text-muted-foreground">
                            El menú te espera con nuestras empanadas. Sumá las
                            que quieras y volvé por acá.
                        </p>
                        <Button
                            asChild
                            className="mt-6 min-h-11 rounded-full px-6"
                        >
                            <Link href="/menu">
                                Explorar el menú{' '}
                                <ArrowRight aria-hidden="true" />
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <form
                        id="public-checkout"
                        onSubmit={submit}
                        className="grid min-w-0 gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)] lg:items-start lg:gap-10"
                    >
                        <div className="grid min-w-0 gap-6">
                            <Card className="min-w-0 overflow-hidden rounded-[1.75rem] border-border bg-card shadow-none">
                                <CardHeader>
                                    <p className="text-xs font-bold tracking-[0.16em] text-public-earth-ink uppercase">
                                        01 · Revisá tu elección
                                    </p>
                                    <CardTitle className="font-display text-2xl">
                                        Productos
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    {loadingOptions && (
                                        <p
                                            role="status"
                                            className="text-sm text-muted-foreground"
                                        >
                                            Cargando productos y opciones…
                                        </p>
                                    )}
                                    {optionsError && (
                                        <div
                                            role="alert"
                                            className="rounded-xl border border-destructive/30 p-4 text-sm text-destructive"
                                        >
                                            <p>{optionsError}</p>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="mt-3 min-h-11"
                                                onClick={() => {
                                                    setLoadingOptions(true);
                                                    setOptionsError('');
                                                    setReloadOptions(
                                                        (value) => value + 1,
                                                    );
                                                }}
                                            >
                                                Reintentar carga
                                            </Button>
                                        </div>
                                    )}
                                    {(missingProducts.length > 0 ||
                                        missingAddons.length > 0) && (
                                        <p
                                            role="alert"
                                            className="rounded-xl border border-destructive/30 p-4 text-sm text-destructive"
                                        >
                                            Hay productos o adicionales que ya
                                            no están disponibles. Quitalos del
                                            carrito para continuar.
                                        </p>
                                    )}
                                    {cart.items.map((item) => {
                                        const product = catalog.find(
                                            (entry) =>
                                                entry.slug ===
                                                item.product_slug,
                                        );

                                        return (
                                            <div
                                                key={item.product_slug}
                                                className="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-border pb-4 last:border-b-0"
                                            >
                                                <div className="min-w-0 flex-1 basis-36">
                                                    <p className="font-semibold break-words">
                                                        {product?.name ??
                                                            item.product_slug}
                                                    </p>
                                                    {!loadingOptions &&
                                                        !optionsError &&
                                                        !product && (
                                                            <p className="mt-1 text-xs font-medium text-destructive">
                                                                Ya no disponible
                                                            </p>
                                                        )}
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        className="size-11"
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
                                                        aria-label={`Cantidad de ${product?.name ?? item.product_slug}`}
                                                        className="w-7 text-center text-sm font-bold tabular-nums"
                                                    >
                                                        {item.quantity}
                                                    </output>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        className="size-11"
                                                        disabled={
                                                            !product &&
                                                            !loadingOptions &&
                                                            !optionsError
                                                        }
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
                                                        className="size-11 text-muted-foreground hover:text-destructive"
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
                                    <fieldset className="grid gap-2 border-t border-border pt-4">
                                        <legend className="text-sm font-medium">
                                            Preparación
                                        </legend>
                                        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4">
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
                                    {missingAddons.map((addon) => (
                                        <div
                                            key={addon.addon_slug}
                                            className="flex min-w-0 items-center justify-between gap-3 border-t border-border pt-3 text-sm"
                                        >
                                            <span className="min-w-0 break-words text-destructive">
                                                {addon.addon_slug} · Ya no
                                                disponible
                                            </span>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="min-h-11 shrink-0"
                                                onClick={() =>
                                                    cart.setAddonQuantity(
                                                        addon.addon_slug,
                                                        0,
                                                    )
                                                }
                                            >
                                                Quitar
                                            </Button>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                            <Card className="rounded-[1.75rem] border-border bg-card shadow-none">
                                <CardHeader>
                                    <p className="text-xs font-bold tracking-[0.16em] text-public-earth-ink uppercase">
                                        02 · Para contactarte
                                    </p>
                                    <CardTitle className="font-display text-2xl">
                                        Tus datos
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <Field
                                        id="checkout-name"
                                        label="Nombre"
                                        value={name}
                                        onChange={setName}
                                        required
                                        autoComplete="name"
                                    />
                                    <Field
                                        id="checkout-phone"
                                        label="Teléfono"
                                        value={phone}
                                        onChange={setPhone}
                                        required
                                        inputMode="tel"
                                        type="tel"
                                        autoComplete="tel"
                                    />
                                </CardContent>
                            </Card>
                            <Card className="rounded-[1.75rem] border-border bg-card shadow-none">
                                <CardHeader>
                                    <p className="text-xs font-bold tracking-[0.16em] text-public-earth-ink uppercase">
                                        03 · Cómo lo recibís
                                    </p>
                                    <CardTitle className="font-display text-2xl">
                                        Entrega
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div
                                        role="group"
                                        aria-label="Tipo de entrega"
                                        className="flex flex-wrap gap-2"
                                    >
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
                                            className="min-h-11 rounded-full px-6"
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
                                            className="min-h-11 rounded-full px-6"
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
                                            autoComplete="street-address"
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
                        <aside
                            className="grid min-w-0 content-start gap-4 lg:sticky lg:top-6"
                            aria-label="Resumen y finalización"
                        >
                            <p className="text-xs font-bold tracking-[0.16em] text-public-earth-ink uppercase">
                                04 · Revisá el total
                            </p>
                            <PriceSummary
                                quote={quote}
                                loading={loadingQuote}
                            />
                            {quoteError && (
                                <div
                                    role="alert"
                                    className="rounded-xl border border-destructive/30 p-4 text-sm text-destructive"
                                >
                                    <p>
                                        No pudimos actualizar el precio:{' '}
                                        {quoteError}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="mt-3 min-h-11"
                                        onClick={() =>
                                            setQuoteRetry((value) => value + 1)
                                        }
                                    >
                                        Reintentar precio
                                    </Button>
                                </div>
                            )}
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
                                disabled={!canSubmit}
                                className="hidden min-h-12 rounded-full sm:inline-flex"
                            >
                                {submitting
                                    ? 'Creando pedido…'
                                    : 'Confirmar y abrir WhatsApp'}
                            </Button>
                            <p className="text-xs leading-5 text-muted-foreground">
                                Al continuar, guardamos el pedido y abrimos
                                WhatsApp con un mensaje preparado. Tenés que
                                enviarlo vos; no queda confirmado
                                automáticamente.
                            </p>
                        </aside>
                    </form>
                )}
                {cart.items.length > 0 && (
                    <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-background/95 px-4 py-3 backdrop-blur-sm sm:hidden">
                        <div className="mx-auto max-w-7xl">
                            <Button
                                form="public-checkout"
                                type="submit"
                                size="lg"
                                disabled={!canSubmit}
                                className="min-h-12 w-full rounded-full"
                            >
                                {submitting
                                    ? 'Creando pedido…'
                                    : 'Confirmar y abrir WhatsApp'}
                            </Button>
                        </div>
                    </div>
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
    autoComplete?: string;
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
